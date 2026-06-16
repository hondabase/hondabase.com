@extends('errors.base', [
    'code' => '500',
    'title' => __('Server Error'),
    'message' => __('Something went wrong on our servers. Diagnostic system is investigating.')
])
