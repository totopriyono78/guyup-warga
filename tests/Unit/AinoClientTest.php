<?php

namespace Tests\Unit;

use App\Services\AinoClient;
use PHPUnit\Framework\TestCase;

class AinoClientTest extends TestCase
{
    public function test_pemetaan_status_query_payment(): void
    {
        $this->assertSame('paid', AinoClient::statusDariInquiry(['transactionStatusDesc' => 'paid']));
        $this->assertSame('pending', AinoClient::statusDariInquiry(['transactionStatusDesc' => 'pending']));
        $this->assertSame('failed', AinoClient::statusDariInquiry(['transactionStatusDesc' => 'fail']));
        $this->assertSame('paid', AinoClient::statusDariInquiry(['latestTransactionStatus' => '02']));
        $this->assertSame('canceled', AinoClient::statusDariInquiry(['latestTransactionStatus' => '05']));
    }

    public function test_nominal_dan_reference(): void
    {
        $this->assertSame(10000, AinoClient::nominal(['amount' => ['value' => 10000, 'currency' => 'IDR']]));
        $this->assertSame(5000, AinoClient::nominal(['amount' => '5000']));
        $this->assertSame('R1', AinoClient::referenceNo(['referenceNumber' => 'R1']));
        $this->assertSame('R2', AinoClient::referenceNo(['referenceNo' => 'R2']));
    }
}
