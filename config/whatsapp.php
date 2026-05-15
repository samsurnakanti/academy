<?php
declare(strict_types=1);

const WHATSAPP_BUSINESS_ACCOUNT_ID = '467530709787499';
const WHATSAPP_PHONE_NUMBER_ID = '886937197845294';
const WHATSAPP_GRAPH_VERSION = 'v20.0';
const WHATSAPP_OTP_TEMPLATE_DEFAULT = 'elldy_academy_otp';

function whatsapp_access_token(): string
{
    return (string) getenv('WHATSAPP_ACCESS_TOKEN');
}

function whatsapp_otp_template_name(): string
{
    return (string) (getenv('WHATSAPP_OTP_TEMPLATE') ?: WHATSAPP_OTP_TEMPLATE_DEFAULT);
}
