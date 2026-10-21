<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GstinLookup
{
    public function lookup(string $gstin): array
    {
        $gstin = strtoupper(trim($gstin));
        if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)) {
            throw ValidationException::withMessages(['gstin' => 'Enter a valid 15-character GSTIN.']);
        }
        if (!config('services.gstin.key')) {
            abort(503, 'GST lookup is not configured. Ask your administrator to configure the free-credit API key.');
        }
        try {
            $response = Http::acceptJson()->withHeaders(['x-api-key' => config('services.gstin.key')])
                ->connectTimeout(5)->timeout(20)
                ->get('https://www.gstinapi.in/v1/gstin/'.$gstin, ['include' => 'profile']);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            abort(503, 'GST lookup is temporarily unavailable. Please try again.');
        }
        if (!$response->successful()) {
            abort(match ($response->status()) { 404 => 404, 429 => 429, default => 503 }, match ($response->status()) {
                400 => 'The GST provider rejected this GSTIN. Check the number.',
                404 => 'No registered business was found for this GSTIN.',
                401, 403 => 'The GST API key is invalid or inactive. Contact your administrator.',
                402 => 'GST lookup credits have run out. You can still enter customer details manually.',
                429 => 'Too many GST searches. Please wait and try again.',
                default => 'GST lookup is temporarily unavailable. Please try again.',
            });
        }
        $data = $response->json('data');
        abort_unless($response->json('success') === true && is_array($data)
            && strtoupper((string) ($data['gstin'] ?? '')) === $gstin
            && !empty($data['legal_name']), 502, 'The GST provider returned incomplete details. Please try again.');
        $address = is_array($data['address_details'] ?? null) ? $data['address_details'] : [];
        $text = fn ($value) => is_scalar($value) ? (string) $value : '';
        return [
            'gst_no' => $gstin, 'pan_no' => substr($gstin, 2, 10),
            'name' => $text($data['trade_name'] ?? null) ?: $text($data['legal_name']),
            'legal_name' => $text($data['legal_name']), 'status' => $text($data['status'] ?? null),
            'address' => $text($data['address'] ?? null),
            'city' => $text($address['city'] ?? null),
            'area' => $text($address['locality'] ?? null),
            'landmark' => $text($address['landmark'] ?? null),
            'pincode' => $text($data['pincode'] ?? $address['pincode'] ?? null),
            'state' => $text($address['state'] ?? null),
            'entity_type' => $text($data['business_constitution'] ?? null),
            'registration_type' => $text($data['taxpayer_type'] ?? null),
            'registration_date' => $text($data['registration_date'] ?? null),
            'department' => $text($data['centre_jurisdiction'] ?? null),
            'nature_of_business' => is_array($data['nature_of_business'] ?? null)
                ? implode(', ', array_filter($data['nature_of_business'], 'is_string')) : $text($data['nature_of_business'] ?? null),
        ];
    }
}
