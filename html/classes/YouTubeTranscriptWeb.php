<?php

class YouTubeTranscriptWeb {
    
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
     * Get transcript using web scraping approach
     */
    public function getTranscript($videoUrl) {
        try {
            $videoId = $this->extractVideoId($videoUrl);
            if (!$videoId) {
                return "Error: Invalid YouTube URL";
            }
            
            // Use file_get_contents with context to fetch the page
            $context = stream_context_create([
                'http' => [
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                               "Accept-Language: en-US,en;q=0.9\r\n",
                    'timeout' => 30,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);
            
            // First, try to get the video page
            $videoPageUrl = "https://www.youtube.com/watch?v=" . $videoId;
            $pageContent = @file_get_contents($videoPageUrl, false, $context);
            
            if (!$pageContent) {
                return "Error: Could not fetch video page";
            }
            
            // Look for the ytInitialPlayerResponse
            if (preg_match('/ytInitialPlayerResponse\s*=\s*({.+?});/s', $pageContent, $matches)) {
                $playerData = json_decode($matches[1], true);
                
                error_log("Found player data, checking for captions...");
                
                // Check if captions are available
                if (isset($playerData['captions']['playerCaptionsTracklistRenderer']['captionTracks'])) {
                    $tracks = $playerData['captions']['playerCaptionsTracklistRenderer']['captionTracks'];
                    error_log("Found " . count($tracks) . " caption tracks");
                    
                    // Find English track or first available
                    $captionUrl = null;
                    foreach ($tracks as $track) {
                        if (isset($track['baseUrl'])) {
                            if (isset($track['languageCode']) && $track['languageCode'] == 'en') {
                                $captionUrl = $track['baseUrl'];
                                break;
                            } elseif (!$captionUrl) {
                                $captionUrl = $track['baseUrl'];
                            }
                        }
                    }
                    
                    if ($captionUrl) {
                        // Fetch the captions
                        $captionData = @file_get_contents($captionUrl, false, $context);
                        
                        if ($captionData) {
                            // Parse the XML response
                            $xml = @simplexml_load_string($captionData);
                            if ($xml) {
                                $transcript = [];
                                foreach ($xml->text as $text) {
                                    $line = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5);
                                    $line = strip_tags($line);
                                    $line = trim($line);
                                    if ($line) {
                                        $transcript[] = $line;
                                    }
                                }
                                return implode(' ', $transcript);
                            }
                        }
                    }
                }
            }
            
            // Try alternative method - look for captions in initial data
            if (preg_match('/ytInitialData\s*=\s*({.+?});/s', $pageContent, $matches)) {
                $initialData = json_decode($matches[1], true);
                // Sometimes captions info is in initial data
                error_log("Found ytInitialData, checking for captions...");
            }
            
            return "No captions available for this video";
            
        } catch (Exception $e) {
            error_log("Transcript fetch error: " . $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }
    
    /**
     * Update lesson with transcript
     */
    public function updateLessonTranscript($lessonId, $transcript) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $pdo = getDB();
            
            $stmt = $pdo->prepare("UPDATE lessons SET video_transcript = ? WHERE id = ?");
            $result = $stmt->execute([$transcript, $lessonId]);
            
            return $result;
        } catch (PDOException $e) {
            error_log("Database error updating transcript: " . $e->getMessage());
            return false;
        }
    }
}