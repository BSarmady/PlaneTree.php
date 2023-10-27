<?php

namespace config;

use logger\logger;
use crypto\AES256;

/**
 * Load and saves application config
 */
class config {

    // App Settings
    const APP_NAME = '##APP_NAME##'; // This is translated
    const SETTINGS_TIMEZONE = 'Asia/Kuala_Lumpur';
    const DEFAULT_LANGUAGE = 'en';
    const COPYRIGHT_NOTICE = 'Copyright &copy; 2023 <a href="https://bob.sarmady.com" class="text-decoration-none">Sarmady.com</a> All rights reserved.';

    // database config
    const MYSQL_HOST = 'database IP';
    const MYSQL_DATABASE = 'database name';
    const MYSQL_PORT = 3306;
    const MYSQL_USERNAME = 'database user';
    const MYSQL_PASSWORD = 'database password';

    //Cache settings
    const ENABLE_CACHING = false;

    //Log Settings
    const LOG_LEVEL = logger::LOG_LEVEL_DEBUG;

    //  UI Settings
    const DEFAULT_TEMPLATE = 'index';

    // Authentication
    const GUEST_ROLE = 'guest';
    const SUPER_ADMIN_ROLE = 'root_administrators';
    const DORMANT_PERIOD = 90;
    const DORMANT_AFTER_CREATED = 5;
    const MAX_FAILED_SIGN_IN_COUNT = 5;
    const PASSWORD_EXPIRES_IN_DAYS = 120;
    const PASSWORD_MIN_LEN = 6;
    const PASSWORD_MAX_LEN = 16;
    const TWO_STEP_SIGN_IN = false;
    const ENABLE_CAPTCHA = false;
    const CAPTCHA_CASE_SENSITIVE = false;
    const ENABLE_QA_RECOVERY = true;
    const ENABLE_EMAIL_RECOVERY = false;
    const ENABLE_SMS_RECOVERY = false;

    // Access white list
    const CORS_WHITE_LIST = ['http://localhost'];
    const IP_WHITE_LIST = [];
    const IP_BLACK_LIST = [];
    const MAX_POST_SIZE = 10485760; // 10 Meg


    const ENABLE_PERFORMANCE_COUNTERS = true;

}


