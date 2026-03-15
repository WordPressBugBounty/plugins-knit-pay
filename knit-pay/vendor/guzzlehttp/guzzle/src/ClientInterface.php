<?php
/**
 * Backward Compatibility Stub File
 * 
 * This file exists as a workaround for backward compatibility with older versions
 * of Knit Pay Pro (< 1.5.8.1).
 * 
 * CONTEXT:
 * Older versions of knit-pay-pro.php (lines 109-111) attempt to include three Guzzle files:
 * - vendor/guzzlehttp/guzzle/src/ClientInterface.php
 * - vendor/guzzlehttp/guzzle/src/Client.php
 * - vendor/guzzlehttp/guzzle/src/Utils.php
 * 
 * These files were removed in newer versions of Knit Pay to add support for WordPress Requests PSR
 * 
 * SOLUTION:
 * This blank file prevents "file not found" errors when old versions try to include it.
 * New versions of Knit Pay Pro check for file existence before including.
 * 
 * This file can be removed once all users have upgraded to Knit Pay Pro 1.5.8.1 or later.
 * 
 * @package KnitPay
 * @since 9.2.3.0
 */

// This file is intentionally left blank/minimal for backward compatibility
// Delete these files once all users have upgraded to Knit Pay Pro 1.5.8.1 or later or after Dec 2026.