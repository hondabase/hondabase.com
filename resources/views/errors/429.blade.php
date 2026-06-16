@extends('errors.base', [
    'code' => '429',
    'title' => __('Too Many Requests'),
    'message' => (isset($exception) && $exception->getMessage()) ? $exception->getMessage() : __('You have sent too many requests. Diagnostic port is temporarily throttled.')
])
