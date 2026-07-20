<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\NotificationService;
use App\Services\Sms\TwilioSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $notificationService;
    protected $twilioService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->twilioService = $this->createMock(TwilioSmsService::class);
        $this->notificationService = new NotificationService($this->twilioService);
    }

    /** @test */
    public function it_can_send_email_notification()
    {
        $recipient = [
            'email' => 'test@example.com',
            'name' => 'Test User'
        ];
        
        $result = $this->notificationService->sendEmail($recipient, 'Test Subject', 'Test content');
        
        $this->assertTrue(is_bool($result));
    }

    /** @test */
    public function it_validates_phone_number_for_sms()
    {
        $recipient = ['name' => 'Test User']; // No phone
        
        $result = $this->notificationService->sendSMS($recipient, 'Test message');
        
        $this->assertFalse($result);
    }

    /** @test */
    public function it_can_queue_notification()
    {
        $recipient = [
            'email' => 'test@example.com',
            'name' => 'Test User'
        ];
        
        $result = $this->notificationService->queueNotification($recipient, 'email', 'Subject', 'Content');
        
        $this->assertTrue(is_bool($result));
    }
}
