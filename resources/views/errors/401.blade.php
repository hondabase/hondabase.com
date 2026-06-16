@extends('errors.base', [
    'code' => '401',
    'title' => __('Unauthorized'),
    'message' => (isset($exception) && $exception->getMessage()) ? $exception->getMessage() : __('Authentication is required to access this resource.')
])
