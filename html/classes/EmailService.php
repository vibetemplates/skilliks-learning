<?php
/**
 * Email Service Class
 * 
 * Handles email sending using MailerSend
 */

use MailerSend\MailerSend;
use MailerSend\Helpers\Builder\Personalization;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\Helpers\Builder\EmailParams;

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';
require_once dirname(dirname(__FILE__)) . '/config/constants.php';

class EmailService {
    private $mailersend;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $apiToken = MAILERSEND_API_TOKEN;
        if (!$apiToken) {
            throw new Exception('MailerSend API token not configured');
        }
        
        $this->mailersend = new MailerSend(['api_key' => $apiToken]);
        $this->fromEmail = EMAIL_FROM;
        $this->fromName = EMAIL_FROM_NAME;
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($toEmail, $firstName, $resetToken) {
        try {
            // Create reset URL
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'stage.skilliks.ai';
            $resetUrl = 'https://' . $host . '/reset-password.php?token=' . $resetToken;
            
            // Create personalization data
            $personalization = [
                new Personalization($toEmail, [
                    'customer' => [
                        'token' => $resetToken,
                        'first_name' => $firstName,
                        'reset_url' => $resetUrl
                    ]
                ])
            ];
            
            // Create recipient
            $recipients = [
                new Recipient($toEmail, $firstName),
            ];
            
            // Build email parameters
            $emailParams = (new EmailParams())
                ->setFrom($this->fromEmail)
                ->setFromName($this->fromName)
                ->setRecipients($recipients)
                ->setSubject('Reset Your Password - SkillikS')
                ->setTemplateId('z86org8yw5klew13')
                ->setPersonalization($personalization);
            
            // Send email
            $response = $this->mailersend->email->send($emailParams);
            
            return [
                'success' => true,
                'message' => 'Password reset email sent successfully'
            ];
            
        } catch (Exception $e) {
            error_log('MailerSend error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send email verification email
     */
    public function sendVerificationEmail($toEmail, $firstName, $verificationToken) {
        try {
            // Create verification URL
            $verifyUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/verify-email.php?token=' . $verificationToken;
            
            // Create personalization data
            $personalization = [
                new Personalization($toEmail, [
                    'customer' => [
                        'token' => $verificationToken,
                        'first_name' => $firstName,
                        'verify_url' => $verifyUrl
                    ]
                ])
            ];
            
            // Create recipient
            $recipients = [
                new Recipient($toEmail, $firstName),
            ];
            
            // Build email parameters
            $emailParams = (new EmailParams())
                ->setFrom($this->fromEmail)
                ->setFromName($this->fromName)
                ->setRecipients($recipients)
                ->setSubject('Verify Your Email - SkillikS')
                ->setTemplateId('z86org8yw5klew13') // You may want a different template for verification
                ->setPersonalization($personalization);
            
            // Send email
            $response = $this->mailersend->email->send($emailParams);
            
            return [
                'success' => true,
                'message' => 'Verification email sent successfully'
            ];
            
        } catch (Exception $e) {
            error_log('MailerSend error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send welcome email
     */
    public function sendWelcomeEmail($toEmail, $firstName) {
        try {
            // Create personalization data
            $personalization = [
                new Personalization($toEmail, [
                    'customer' => [
                        'first_name' => $firstName
                    ]
                ])
            ];
            
            // Create recipient
            $recipients = [
                new Recipient($toEmail, $firstName),
            ];
            
            // Build email parameters
            $emailParams = (new EmailParams())
                ->setFrom($this->fromEmail)
                ->setFromName($this->fromName)
                ->setRecipients($recipients)
                ->setSubject('Welcome to SkillikS!')
                ->setTemplateId('z86org8yw5klew13') // You may want a different template for welcome
                ->setPersonalization($personalization);
            
            // Send email
            $response = $this->mailersend->email->send($emailParams);
            
            return [
                'success' => true,
                'message' => 'Welcome email sent successfully'
            ];
            
        } catch (Exception $e) {
            error_log('MailerSend error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send generic email with custom subject and content
     */
    public function sendEmail($toEmail, $toName, $subject, $htmlContent, $textContent = '') {
        try {
            // Create recipient
            $recipients = [
                new Recipient($toEmail, $toName),
            ];
            
            // Build email parameters
            $emailParams = (new EmailParams())
                ->setFrom($this->fromEmail)
                ->setFromName($this->fromName)
                ->setRecipients($recipients)
                ->setSubject($subject)
                ->setHtml($htmlContent)
                ->setText($textContent ?: strip_tags($htmlContent));
            
            // Send email
            $response = $this->mailersend->email->send($emailParams);
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
            
        } catch (Exception $e) {
            error_log('MailerSend error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }
}