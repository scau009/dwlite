<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:test-open-api',
    description: 'Test Open API endpoints for availability and authentication',
)]
class TestOpenApiCommand extends Command
{
    /** @var array<array{method: string, path: string, permission: string, description: string}> */
    private const MERCHANT_ENDPOINTS = [
        // Settlement
        ['method' => 'GET', 'path' => '/api/v1/open/merchant/settlements/summary', 'permission' => 'settlement:read', 'description' => 'Settlement summary'],
        ['method' => 'GET', 'path' => '/api/v1/open/merchant/settlements', 'permission' => 'settlement:read', 'description' => 'List settlements'],
        // Fulfillment
        ['method' => 'GET', 'path' => '/api/v1/open/merchant/fulfillments', 'permission' => 'fulfillment:read', 'description' => 'List fulfillments'],
        // Inventory
        ['method' => 'GET', 'path' => '/api/v1/open/merchant/inventory', 'permission' => 'merchant_inventory:read', 'description' => 'List inventory'],
        // Inbound
        ['method' => 'GET', 'path' => '/api/v1/open/merchant/inbound/orders', 'permission' => 'merchant_inbound:read', 'description' => 'List inbound orders'],
        // Listings
        ['method' => 'GET', 'path' => '/api/v1/open/merchant/listings', 'permission' => 'listing:read', 'description' => 'List listings'],
        // Webhooks
        ['method' => 'GET', 'path' => '/api/v1/open/merchant/webhooks', 'permission' => 'webhook:manage', 'description' => 'List webhooks'],
    ];

    /** @var array<array{method: string, path: string, permission: string, description: string}> */
    private const WAREHOUSE_ENDPOINTS = [
        // Inbound
        ['method' => 'GET', 'path' => '/api/v1/open/warehouse/inbound/orders', 'permission' => 'inbound:read', 'description' => 'List inbound orders'],
        // Outbound
        ['method' => 'GET', 'path' => '/api/v1/open/warehouse/outbound/orders', 'permission' => 'outbound:read', 'description' => 'List outbound orders'],
        // Inventory
        ['method' => 'GET', 'path' => '/api/v1/open/warehouse/inventory', 'permission' => 'inventory:read', 'description' => 'List inventory'],
        // Webhooks
        ['method' => 'GET', 'path' => '/api/v1/open/warehouse/webhooks', 'permission' => 'webhook:manage', 'description' => 'List webhooks'],
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('merchant-key-id', null, InputOption::VALUE_OPTIONAL, 'Merchant API Key ID')
            ->addOption('merchant-key-secret', null, InputOption::VALUE_OPTIONAL, 'Merchant API Key Secret')
            ->addOption('warehouse-key-id', null, InputOption::VALUE_OPTIONAL, 'Warehouse API Key ID')
            ->addOption('warehouse-key-secret', null, InputOption::VALUE_OPTIONAL, 'Warehouse API Key Secret')
            ->addOption('base-url', null, InputOption::VALUE_OPTIONAL, 'API Base URL', 'http://localhost:8000')
            ->addOption('skip-merchant', null, InputOption::VALUE_NONE, 'Skip merchant API tests')
            ->addOption('skip-warehouse', null, InputOption::VALUE_NONE, 'Skip warehouse API tests')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Show debug information for signature generation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('DWLite Open API Test Suite');

        $baseUrl = rtrim((string) $input->getOption('base-url'), '/');
        $io->text(sprintf('Base URL: <info>%s</info>', $baseUrl));
        $io->newLine();

        $merchantKeyId = $input->getOption('merchant-key-id');
        $merchantKeySecret = $input->getOption('merchant-key-secret');
        $warehouseKeyId = $input->getOption('warehouse-key-id');
        $warehouseKeySecret = $input->getOption('warehouse-key-secret');

        $skipMerchant = $input->getOption('skip-merchant');
        $skipWarehouse = $input->getOption('skip-warehouse');

        $totalTests = 0;
        $passedTests = 0;
        $failedTests = 0;

        // Test Merchant APIs
        if (!$skipMerchant) {
            if ($merchantKeyId !== null && $merchantKeySecret !== null) {
                $io->section('Testing Merchant APIs');
                $io->text(sprintf('API Key: <info>%s</info>', $this->maskKey((string) $merchantKeyId)));
                $io->newLine();

                $results = $this->testEndpoints(
                    self::MERCHANT_ENDPOINTS,
                    $baseUrl,
                    (string) $merchantKeyId,
                    (string) $merchantKeySecret,
                    $output
                );

                $totalTests += $results['total'];
                $passedTests += $results['passed'];
                $failedTests += $results['failed'];
            } else {
                $io->warning('Merchant API credentials not provided. Skipping merchant API tests.');
                $io->text('Use --merchant-key-id and --merchant-key-secret to provide credentials.');
                $io->newLine();
            }
        }

        // Test Warehouse APIs
        if (!$skipWarehouse) {
            if ($warehouseKeyId !== null && $warehouseKeySecret !== null) {
                $io->section('Testing Warehouse APIs');
                $io->text(sprintf('API Key: <info>%s</info>', $this->maskKey((string) $warehouseKeyId)));
                $io->newLine();

                $results = $this->testEndpoints(
                    self::WAREHOUSE_ENDPOINTS,
                    $baseUrl,
                    (string) $warehouseKeyId,
                    (string) $warehouseKeySecret,
                    $output
                );

                $totalTests += $results['total'];
                $passedTests += $results['passed'];
                $failedTests += $results['failed'];
            } else {
                $io->warning('Warehouse API credentials not provided. Skipping warehouse API tests.');
                $io->text('Use --warehouse-key-id and --warehouse-key-secret to provide credentials.');
                $io->newLine();
            }
        }

        // Summary
        if ($totalTests > 0) {
            $io->section('Summary');

            $percentage = round(($passedTests / $totalTests) * 100, 1);

            $summaryTable = new Table($output);
            $summaryTable->setHeaders(['Metric', 'Value']);
            $summaryTable->addRows([
                ['Total Tests', (string) $totalTests],
                ['Passed', sprintf('<fg=green>%d</>', $passedTests)],
                ['Failed', sprintf('<fg=%s>%d</>', $failedTests > 0 ? 'red' : 'green', $failedTests)],
                ['Success Rate', sprintf('%s%%', $percentage)],
            ]);
            $summaryTable->render();

            $io->newLine();

            if ($failedTests === 0) {
                $io->success('All tests passed!');

                return Command::SUCCESS;
            }

            $io->error(sprintf('%d test(s) failed.', $failedTests));

            return Command::FAILURE;
        }

        $io->warning('No tests were run. Please provide API credentials.');

        return Command::INVALID;
    }

    /**
     * Test a set of endpoints.
     *
     * @param array<array{method: string, path: string, permission: string, description: string}> $endpoints
     *
     * @return array{total: int, passed: int, failed: int}
     */
    private function testEndpoints(
        array $endpoints,
        string $baseUrl,
        string $keyId,
        string $keySecret,
        OutputInterface $output
    ): array {
        $passed = 0;
        $failed = 0;

        $table = new Table($output);
        $table->setHeaders(['Status', 'Method', 'Path', 'Response', 'Time']);

        foreach ($endpoints as $endpoint) {
            $result = $this->testEndpoint(
                $endpoint['method'],
                $baseUrl.$endpoint['path'],
                $endpoint['path'],
                $keyId,
                $keySecret
            );

            if ($result['success']) {
                ++$passed;
                $statusIcon = '<fg=green>PASS</>';
                $statusCode = sprintf('<fg=green>%d %s</>', $result['statusCode'], $result['statusText']);
            } else {
                ++$failed;
                $statusIcon = '<fg=red>FAIL</>';
                $statusCode = sprintf('<fg=red>%d %s</>', $result['statusCode'], $result['statusText']);
            }

            $time = sprintf('%.0fms', $result['time'] * 1000);

            $table->addRow([
                $statusIcon,
                $endpoint['method'],
                $endpoint['path'],
                $statusCode,
                $time,
            ]);

            // Show error details if failed
            if (!$result['success'] && isset($result['error'])) {
                $table->addRow([
                    '',
                    '',
                    sprintf('<fg=yellow>  Error: %s</>', $result['error']),
                    '',
                    '',
                ]);
            }
        }

        $table->render();
        $output->writeln('');

        return [
            'total' => count($endpoints),
            'passed' => $passed,
            'failed' => $failed,
        ];
    }

    /**
     * Test a single endpoint.
     *
     * @return array{success: bool, statusCode: int, statusText: string, time: float, error?: string}
     */
    private function testEndpoint(
        string $method,
        string $url,
        string $path,
        string $keyId,
        string $keySecret
    ): array {
        $timestamp = (string) time();
        $nonce = $this->generateNonce();
        $body = '';

        // Generate signature
        $signature = $this->generateSignature($method, $path, $timestamp, $nonce, $body, $keySecret);

        $headers = [
            'X-Api-Key' => $keyId,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $signature,
            'Accept' => 'application/json',
        ];

        $startTime = microtime(true);

        try {
            $response = $this->httpClient->request($method, $url, [
                'headers' => $headers,
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $endTime = microtime(true);

            $statusText = $this->getStatusText($statusCode);

            // Consider 2xx and 404 (no data) as success for read endpoints
            $success = $statusCode >= 200 && $statusCode < 300;

            $result = [
                'success' => $success,
                'statusCode' => $statusCode,
                'statusText' => $statusText,
                'time' => $endTime - $startTime,
            ];

            // Extract error message if failed
            if (!$success) {
                $content = $response->getContent(false);
                $data = json_decode($content, true);
                if (is_array($data) && isset($data['error']['message'])) {
                    $result['error'] = $data['error']['message'];
                } elseif (is_array($data) && isset($data['error']['code'])) {
                    $result['error'] = $data['error']['code'];
                } elseif (is_array($data) && isset($data['detail'])) {
                    $result['error'] = $data['detail'];
                } else {
                    // Show raw response for debugging
                    $result['error'] = substr($content, 0, 100);
                }
            }

            return $result;
        } catch (\Throwable $e) {
            $endTime = microtime(true);

            return [
                'success' => false,
                'statusCode' => 0,
                'statusText' => 'Connection Error',
                'time' => $endTime - $startTime,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate HMAC-SHA256 signature following the same algorithm as SignatureService.
     */
    private function generateSignature(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $body,
        string $secret
    ): string {
        $bodyHash = $body !== '' ? hash('sha256', $body) : '';

        $canonicalString = implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            $bodyHash,
        ]);

        return 'sha256='.hash_hmac('sha256', $canonicalString, $secret);
    }

    /**
     * Generate a unique nonce (UUID v4).
     */
    private function generateNonce(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0x0FFF) | 0x4000,
            random_int(0, 0x3FFF) | 0x8000,
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF)
        );
    }

    /**
     * Mask API key for display.
     */
    private function maskKey(string $key): string
    {
        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 4).'****'.substr($key, -4);
    }

    /**
     * Get HTTP status text.
     */
    private function getStatusText(int $statusCode): string
    {
        return match ($statusCode) {
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => 'Unknown',
        };
    }
}
