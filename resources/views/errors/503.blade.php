@extends('errors.base', [
    'code' => '503',
    'title' => __('Service Unavailable'),
    'message' => (isset($exception) && $exception->getMessage()) ? $exception->getMessage() : __('The server is temporarily down for maintenance or overloaded. Please try again later.')
])
