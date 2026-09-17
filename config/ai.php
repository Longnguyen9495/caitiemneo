<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trợ lý quản lý AI
    |--------------------------------------------------------------------------
    |
    | Provider sử dụng giao thức OpenAI-compatible để có thể kết nối Qwen hoặc
    | một dịch vụ tương thích khác mà không khóa ứng dụng vào một nhà cung cấp.
    | Khóa API chỉ được đọc từ môi trường và tuyệt đối không được ghi vào log,
    | lịch sử hội thoại hay prompt gửi ngược về giao diện.
    |
    */
    'enabled' => (bool) env('AI_ENABLED', false),

    'provider' => env('AI_PROVIDER', 'openai-compatible'),

    'base_url' => rtrim((string) env('AI_BASE_URL', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'), '/'),

    'api_key' => env('AI_API_KEY'),

    'model' => env('AI_MODEL', 'qwen-plus'),

    'timeout' => (int) env('AI_TIMEOUT', 60),

    'connect_timeout' => (int) env('AI_CONNECT_TIMEOUT', 10),

    'history_limit' => (int) env('AI_HISTORY_LIMIT', 20),

    'max_message_length' => (int) env('AI_MAX_MESSAGE_LENGTH', 4000),

    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 3000),

    'temperature' => (float) env('AI_TEMPERATURE', 0.2),
];
