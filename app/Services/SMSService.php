<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SMSService
{
    protected string $baseUrl;
    protected string $apiToken;

    public function __construct()
    {
        $this->baseUrl = "https://sms.iprogtech.com/api/v1";
        $this->apiToken = "e5ae38f927ec77016fb64f08435b340eecf034f4";
    }

    /**
     * Send an SMS message.
     *
     * @param string $phoneNumber
     * @param string $message
     * @return array
     */
    public function sendSms(string $phoneNumber, string $message): array
    {
        $formattedNumber = $this->formatNumber($phoneNumber);

        $response = Http::asJson()
            ->post("{$this->baseUrl}/sms_messages", [
                'api_token'    => $this->apiToken,
                'phone_number' => $formattedNumber,
                'message'      => $message,
            ]);

        if ($response->failed()) {
            return [
                'status'  => $response->status(),
                'message' => 'Failed to send SMS.',
                'error'   => $response->json(),
            ];
        }

        return $response->json();
    }

    private function formatNumber(string $number): string
    {
        $number = preg_replace('/\D/', '', $number); // remove non-digits
        if (str_starts_with($number, '09')) {
            $number = '63' . substr($number, 1);
        }
        return $number;
    }
}
