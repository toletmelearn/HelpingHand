<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;

class CacheServiceTest extends TestCase
{
    protected $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = new CacheService();
        Cache::flush(); // Clear cache before each test
    }

    /** @test */
    public function it_can_cache_student_data()
    {
        $studentId = 1;
        $data = ['name' => 'John Doe', 'roll_number' => '001'];
        
        $this->cacheService->cacheStudent($studentId, $data);
        $cached = $this->cacheService->getStudent($studentId);
        
        $this->assertEquals($data, $cached);
    }

    /** @test */
    public function it_can_cache_teacher_data()
    {
        $teacherId = 1;
        $data = ['name' => 'Jane Smith', 'designation' => 'Senior Teacher'];
        
        $this->cacheService->cacheTeacher($teacherId, $data);
        $cached = $this->cacheService->getTeacher($teacherId);
        
        $this->assertEquals($data, $cached);
    }

    /** @test */
    public function it_can_invalidate_student_cache()
    {
        $studentId = 1;
        $data = ['name' => 'John Doe'];
        
        $this->cacheService->cacheStudent($studentId, $data);
        $this->assertNotNull($this->cacheService->getStudent($studentId));
        
        $this->cacheService->invalidateStudent($studentId);
        $this->assertNull($this->cacheService->getStudent($studentId));
    }

    /** @test */
    public function it_can_remember_callback_result()
    {
        $key = 'test:key';
        $value = 'test value';
        
        $result = $this->cacheService->remember($key, 60, function() use ($value) {
            return $value;
        });
        
        $this->assertEquals($value, $result);
        $this->assertEquals($value, Cache::get($key));
    }

    /** @test */
    public function it_can_get_cache_stats()
    {
        $stats = $this->cacheService->getStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('driver', $stats);
        $this->assertArrayHasKey('prefix', $stats);
    }
}
