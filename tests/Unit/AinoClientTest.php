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

    public function test_variasi_format_respons_inquiry(): void
    {
        $this->assertSame('paid', AinoClient::statusDariInquiry(['transactionStatusDesc' => 'Success']));
        $this->assertSame('paid', AinoClient::statusDariInquiry(['transactionStatusDesc' => 'PAYMENT SUCCESS']));
        $this->assertSame('paid', AinoClient::statusDariInquiry(['data' => ['transactionStatus' => 'PAID']]));
        $this->assertSame('paid', AinoClient::statusDariInquiry(['statusCode' => 3, 'statusLabel' => 'Paid']));
        $this->assertSame('paid', AinoClient::statusDariInquiry(['statusCode' => '3']));
        $this->assertSame('expired', AinoClient::statusDariInquiry(['statusLabel' => 'Expired']));
        $this->assertSame('pending', AinoClient::statusDariInquiry(['transactionStatusDesc' => 'Unpaid']));
        // "status" umum (status panggilan API) tidak boleh dianggap lunas
        $this->assertSame('pending', AinoClient::statusDariInquiry(['status' => 'success', 'data' => ['transactionStatus' => 'pending']]));
        $this->assertSame('pending', AinoClient::statusDariInquiry(['status' => 'success']));

        $this->assertSame(1, AinoClient::nominal(['amount' => ['value' => '1.00', 'currency' => 'IDR']]));
        $this->assertSame(50000, AinoClient::nominal(['data' => ['grossAmount' => '50000']]));
        $this->assertSame('R123', AinoClient::referenceNo(['data' => ['referenceNo' => 'R123']]));
        $this->assertSame('abc', AinoClient::partnerRef(['partnerReferenceNumber' => 'abc']));
    }
}
