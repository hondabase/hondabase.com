@extends('errors.base', [
    'code' => '404',
    'title' => __('Page Not Found'),
    'message' => (isset($exception) && $exception->getMessage()) ? $exception->getMessage() : __('The page you are looking for does not exist or has been moved.')
])
