<?php

namespace App\Services;

use RuntimeException;

/**
 * Ralat Meta yang mengekalkan error code dan error_subcode.
 *
 * Kedua-dua nombor itu penting: ia yang membezakan "call_to_action tidak
 * diterima di atas creative ini" (boleh pulih — jatuh ke laluan kedua)
 * daripada "token luput" (tidak boleh pulih — berhenti dan beritahu user).
 *
 * Nota: Exception sudah memiliki $code sendiri yang tidak boleh diisytihar
 * semula sebagai readonly, jadi kod Meta disimpan sebagai $metaCode.
 */
class MetaApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $metaCode = 0,
        public readonly int $subcode = 0,
        public readonly int $status = 0,
    ) {
        parent::__construct($message, $metaCode);
    }
}
