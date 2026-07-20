<?php

namespace App\Services\Sms;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class TwilioSmsService
{
    protected $client;
    protected $fromNumber;
    protected $enabled;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $this->fromNumber = config('services.twilio.from');
        
        // Check if Twilio is configured
        $this->enabled = !empty($sid) && !empty($token) && !empty($this->fromNumber);
        
        if ($this->enabled) {
            try {
                $this->client = new Client($sid, $token);
            } catch (\Exception $e) {
                Log::error('Failed to initialize Twilio client: ' . $e->getMessage());
                $this->enabled = false;
            }
        }
    }

    /**
     * Send SMS via Twilio
     */
    public function send($to, $message, $options = [])
    {
        if (!$this->enabled) {
            Log::warning('Twilio SMS not configured. SMS not sent.', [
                'to' => $to,
                'message' => substr($message, 0, 50)
            ]);
            return [
                'success' => false,
                'error' => 'Twilio not configured',
                'simulated' => true
            ];
        }

        try {
            // Validate phone number format
            $to = $this->formatPhoneNumber($to);
            
            if (!$to) {
                throw new \Exception('Invalid phone number format');
            }

            // Send SMS via Twilio
            $messageResult = $this->client->messages->create($to, [
                'from' => $this->fromNumber,
                'body' => $message
            ]);

            Log::info('SMS sent successfully via Twilio', [
                'to' => $to,
                'sid' => $messageResult->sid,
                'status' => $messageResult->status
            ]);

            return [
                'success' => true,
                'sid' => $messageResult->sid,
                'status' => $messageResult->status,
                'to' => $to
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send SMS via Twilio: ' . $e->getMessage(), [
                'to' => $to,
                'message' => substr($message, 0, 50)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'to' => $to
            ];
        }
    }

    /**
     * Send bulk SMS
     */
    public function sendBulk(array $recipients, $message, $options = [])
    {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'total' => count($recipients),
            'details' => []
        ];

        foreach ($recipients as $recipient) {
            $result = $this->send($recipient, $message, $options);
            
            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
            
            $results['details'][] = $result;
        }

        Log::info('Bulk SMS completed', $results);

        return $results;
    }

    /**
     * Format phone number to E.164 format
     */
    protected function formatPhoneNumber($number)
    {
        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);
        
        // If number doesn't start with country code, add India's code (+91)
        if (strlen($number) == 10) {
            $number = '91' . $number;
        }
        
        // Add + prefix for E.164 format
        if (!str_starts_with($number, '+')) {
            $number = '+' . $number;
        }
        
        // Validate length (should be between 10-15 digits for E.164)
        if (strlen($number) < 10 || strlen($number) > 16) {
            return false;
        }
        
        return $number;
    }

    /**
     * Check if Twilio is enabled and configured
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Get account information
     */
    public function getAccountInfo()
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $account = $this->client->api->v2010->accounts(config('services.twilio.sid'))->fetch();
            
            return [
                'status' => $account->status,
                'friendly_name' => $account->friendlyName,
                'type' => $account->type,
                'from_number' => $this->fromNumber
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch Twilio account info: ' . $e->getMessage());
            return null;
        }
    }
}
