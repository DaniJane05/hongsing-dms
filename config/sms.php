<?php
/**
 * TextBee SMS Configuration
 * Hong Sing LKRR Delivery Management System
 */

// TextBee API
define('TEXTBEE_API_URL', 'https://api.textbee.dev/api/v1/gateway/send-sms');

// IMPORTANT:
// Replace this with your actual TextBee API key.
// Do NOT share your API key publicly.
define('TEXTBEE_API_KEY', '');

// Optional:
// If you have a specific TextBee device ID, put it here.
// Otherwise leave it empty and TextBee will use the available device.
define('TEXTBEE_DEVICE_ID', '');