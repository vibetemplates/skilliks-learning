<?php
require_once __DIR__ . '/../../vendor/autoload.php';

class YouTubeTranscript {
    private $client;
    private $youtube;
    private $config;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../config/google-api.php';
        $this->initializeClient();
    }
    
    private function initializeClient() {
        $this->client = new Google\Client();
        $this->client->setApplicationName($this->config['application_name']);
        $this->client->setClientId($this->config['oauth_client_id']);
        $this->client->setClientSecret($this->config['oauth_client_secret']);
        $this->client->setScopes([
            Google\Service\YouTube::YOUTUBE_READONLY,
            Google\Service\YouTube::YOUTUBEPARTNER
        ]);
        
        // Try to use OAuth token if available
        $tokenPath = __DIR__ . '/../config/tokens/youtube-token.json';
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $this->client->setAccessToken($accessToken);
            
            // Refresh token if expired
            if ($this->client->isAccessTokenExpired()) {
                if ($this->client->getRefreshToken()) {
                    $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    file_put_contents($tokenPath, json_encode($this->client->getAccessToken()));
                }
            }
        } else {
            // Fall back to API key
            $this->client->setDeveloperKey($this->config['api_key']);
        }
        
        $this->youtube = new Google\Service\YouTube($this->client);
    }
    
    /**
     * Extract YouTube video ID from various URL formats
     */
    public function extractVideoId($url) {
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
            '/youtu\.be\/([a-zA-Z0-9_-]+)/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
            '/youtube\.com\/v\/([a-zA-Z0-9_-]+)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return false;
    }
    
    /**
     * Get transcript for a YouTube video
     */
    public function getTranscript($videoUrl) {
        try {
            $videoId = $this->extractVideoId($videoUrl);
            if (!$videoId) {
                throw new Exception("Invalid YouTube URL: $videoUrl");
            }
            
            error_log("Fetching transcript for video ID: " . $videoId);
            
            // Get video captions
            $captionsResponse = $this->youtube->captions->listCaptions('snippet', $videoId);
            $captions = $captionsResponse->getItems();
            
            error_log("Found " . count($captions) . " captions for video");
            
            if (empty($captions)) {
                return "No captions available for this video.";
            }
            
            // Find English caption or first available
            $captionId = null;
            foreach ($captions as $caption) {
                $snippet = $caption->getSnippet();
                error_log("Caption: " . $caption->getId() . " - Language: " . $snippet->getLanguage() . " - Name: " . $snippet->getName());
                
                if ($snippet->getLanguage() == 'en') {
                    $captionId = $caption->getId();
                    break;
                }
            }
            
            // If no English caption, use first available
            if (!$captionId && !empty($captions)) {
                $captionId = $captions[0]->getId();
                error_log("Using first available caption: " . $captionId);
            }
            
            error_log("Attempting to download caption ID: " . $captionId);
            
            // Download caption
            $response = $this->youtube->captions->download($captionId, ['tfmt' => 'srt']);
            
            error_log("Response type: " . get_class($response));
            
            // Get the actual content from the response
            if ($response instanceof \GuzzleHttp\Psr7\Response) {
                error_log("Response status: " . $response->getStatusCode());
                error_log("Response headers: " . json_encode($response->getHeaders()));
                
                // Get the body and rewind it to ensure we can read it
                $body = $response->getBody();
                $body->rewind();
                $transcript = $body->getContents();
            } elseif (is_string($response)) {
                $transcript = $response;
            } else {
                // If it's not a Response object or string, try to get the body content directly
                error_log("Unexpected response type: " . gettype($response));
                if (method_exists($response, 'getBody')) {
                    $transcript = (string) $response->getBody();
                } else {
                    $transcript = (string) $response;
                }
            }
            
            error_log("Raw transcript length: " . strlen($transcript));
            error_log("First 100 chars of transcript: " . substr($transcript, 0, 100));
            
            // Convert SRT to plain text
            return $this->convertSrtToText($transcript);
            
        } catch (Google\Service\Exception $e) {
            $error = json_decode($e->getMessage(), true);
            error_log("YouTube API Error: " . print_r($error, true));
            
            // If captions.download requires OAuth, try alternative approach
            if (isset($error['error']['errors'][0]['reason']) && 
                $error['error']['errors'][0]['reason'] == 'forbidden') {
                return $this->getTranscriptAlternative($videoId);
            }
            
            return "Error fetching transcript: " . $error['error']['message'];
        } catch (Exception $e) {
            error_log("Transcript Error: " . $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }
    
    /**
     * Alternative method to get transcript using video details
     */
    private function getTranscriptAlternative($videoId) {
        try {
            // Get video details including auto-generated captions
            $response = $this->youtube->videos->listVideos('snippet', ['id' => $videoId]);
            $items = $response->getItems();
            
            if (empty($items)) {
                return "Video not found.";
            }
            
            $video = $items[0];
            $description = $video->getSnippet()->getDescription();
            
            // Return a message indicating manual transcript may be needed
            return "Automatic transcript retrieval requires OAuth authentication. " .
                   "Please use OAuth flow or manually add transcript. " .
                   "Video title: " . $video->getSnippet()->getTitle();
                   
        } catch (Exception $e) {
            return "Error getting video details: " . $e->getMessage();
        }
    }
    
    /**
     * Convert SRT format to plain text
     */
    private function convertSrtToText($srt) {
        // Remove SRT timestamps and formatting
        $lines = explode("\n", $srt);
        $text = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip line numbers and timestamps
            if (!preg_match('/^\d+$/', $line) && 
                !preg_match('/\d{2}:\d{2}:\d{2},\d{3}/', $line) && 
                !empty($line)) {
                $text[] = $line;
            }
        }
        
        return implode(' ', $text);
    }
    
    /**
     * Update lesson with transcript
     */
    public function updateLessonTranscript($lessonId, $transcript) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $pdo = getDB();
            
            error_log("Updating transcript for lesson ID: " . $lessonId);
            error_log("Transcript length: " . strlen($transcript));
            
            $stmt = $pdo->prepare("UPDATE lessons SET video_transcript = ? WHERE id = ?");
            $result = $stmt->execute([$transcript, $lessonId]);
            
            error_log("Update result: " . ($result ? "success" : "failed"));
            error_log("Rows affected: " . $stmt->rowCount());
            
            return $result;
        } catch (PDOException $e) {
            error_log("Database error updating transcript: " . $e->getMessage());
            return false;
        }
    }
}