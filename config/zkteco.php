<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ZKTeco ADMS / Security PUSH Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the ZKTeco Security PUSH protocol (v3.1.2).
    | The initialization response is built in AdmsController::handleCdataGet().
    |
    */
    'iclock' => [
        /*
         * Commands to send via GET /iclock/service/control (Security PUSH).
         * Format: 'C:{CMDID}:COMMAND_DESC COMMAND_DETAIL'
         * Placeholders: {CMDID} auto-replaced, {SN} replaced with device serial.
         *
         * Examples:
         *   'C:{CMDID}:DATA QUERY tablename=transaction,fielddesc=*,filter=*'
         *   'C:{CMDID}:DATA UPDATE user\tPin=999\tName=Test User'
         *   'C:{CMDID}:CONTROL DEVICE 03000000'  (reboot)
         */
        'security_push_commands' => [],

        /*
         * Commands to send via GET /iclock/getrequest (Attendance PUSH).
         * Format: 'C:{CMDID}:COMMAND_DESC COMMAND_DETAIL'
         */
        'getrequest_commands' => [],
    ],
];
