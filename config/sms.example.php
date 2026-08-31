<?php
/*
 * SMS CONFIGURATION — EXAMPLE (Semaphore, semaphore.co)
 * =============================================
 * Copy this file to sms.php (which is gitignored, since it holds a real API
 * key) and fill in your own values from your Semaphore account dashboard.
 * Leave SEMAPHORE_API_KEY blank to disable SMS sending entirely — send_sms()
 * silently skips instead of erroring, so the rest of the app keeps working
 * even before this is configured.
 */

define('SEMAPHORE_API_KEY', '');
// Must be a sender name already approved/registered on your Semaphore
// account — leave blank to use your account's default sender name.
define('SEMAPHORE_SENDER_NAME', '');
