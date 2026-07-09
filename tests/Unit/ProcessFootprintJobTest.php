<?php

namespace TNM\Footprints\Tests\Unit;

use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use TNM\Footprints\Jobs\ProcessFootprintJob;
use TNM\Footprints\Tests\TestCase;

class ProcessFootprintJobTest extends TestCase
{
    protected array $footprintData;

    protected function setUp(): void
    {
        parent::setUp();

        $logDirectory = storage_path('logs');
        if (!File::exists($logDirectory)) {
            File::makeDirectory($logDirectory, 0755, true);
        }

        $this->app['config']->set('logging.channels.footprints', [
            'driver' => 'single',
            'path' => storage_path('logs/footprints.log'),
            'level' => 'debug',
        ]);

        $this->footprintData = [
            'request_id' => 'uuid-1234',
            'user_type' => null,
            'user_id' => null,
            'method' => 'GET',
            'uri' => 'api/test',
            'endpoint' => 'http://localhost/api/test',
            'ip_address' => '127.0.0.1',
            'status_code' => 200,
            'duration_ms' => 150.5,
            'success' => true,
            'request_body' => ['key' => 'value'],
            'response_body' => '{"status":"ok"}',
            'request_headers' => ['User-Agent' => 'Test'],
            'requested_at' => now()->toDateTimeString(),
        ];
    }

    public function test_it_logs_to_database()
    {
        $this->app['config']->set('footprints.channels', ['database']);

        $job = new ProcessFootprintJob($this->footprintData);
        $job->handle();

        $this->assertDatabaseHas('footprints', [
            'request_id' => 'uuid-1234',
            'method' => 'GET',
            'uri' => 'api/test',
            'status_code' => 200,
        ]);
    }

    /**
     * @throws FileNotFoundException
     */
    public function test_it_logs_to_file()
    {
        $this->app['config']->set('footprints.channels', ['file']);

        $logPath = storage_path('logs/footprints.log');

        if (File::exists($logPath)) {
            File::delete($logPath);
        }

        $job = new ProcessFootprintJob($this->footprintData);
        $job->handle();

        $this->assertFileExists($logPath);
        $content = File::get($logPath);
        $this->assertStringContainsString('uuid-1234', $content);
        $this->assertStringContainsString('api\/test', $content);
    }

    /**
     * @throws FileNotFoundException
     */
    public function test_it_handles_multiple_channels()
    {
        $this->app['config']->set('footprints.channels', ['database', 'file']);

        $logPath = storage_path('logs/footprints.log');
        if (File::exists($logPath)) {
            File::delete($logPath);
        }

        $job = new ProcessFootprintJob($this->footprintData);
        $job->handle();

        $this->assertDatabaseHas('footprints', [
            'request_id' => 'uuid-1234',
        ]);

        $this->assertFileExists($logPath);
        $content = File::get($logPath);
        $this->assertStringContainsString('uuid-1234', $content);
    }

    public function test_it_handles_string_channel_config()
    {
        $this->app['config']->set('footprints.channels', 'database,file');

        $logPath = storage_path('logs/footprints.log');
        if (File::exists($logPath)) {
            File::delete($logPath);
        }

        $job = new ProcessFootprintJob($this->footprintData);
        $job->handle();

        $this->assertDatabaseHas('footprints', ['request_id' => 'uuid-1234']);
        $this->assertFileExists($logPath);
    }

    public function test_safe_json_encode_handles_binary_data()
    {
        $this->app['config']->set('footprints.channels', ['database']);

        $invalidUtf8 = "\xB1\x31";

        $data = $this->footprintData;
        $data['request_body'] = ['bad_string' => $invalidUtf8];

        (new ProcessFootprintJob($data))->handle();

        $this->assertDatabaseHas('footprints', [
            'request_id' => 'uuid-1234',
        ]);

        $record = DB::table('footprints')->where('request_id', 'uuid-1234')->first();

        $this->assertStringContainsString('bad_string', $record->request_body);
    }

    /**
     * @throws FileNotFoundException
     */
    public function test_failed_job_triggers_fallback_log()
    {
        Log::partialMock()->shouldReceive('warning')->once();

        (new ProcessFootprintJob($this->footprintData))->failed(new Exception("Something went wrong"));

        $logPath = storage_path('logs/footprints.log');

        $this->assertFileExists($logPath);

        $content = File::get($logPath);

        $this->assertStringContainsString('Something went wrong', $content);
        $this->assertStringContainsString('uuid-1234', $content);
    }
}