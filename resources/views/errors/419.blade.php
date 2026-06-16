@extends('errors.base', [
    'code' => '419',
    'title' => __('Page Expired'),
    'message' => (isset($exception) && $exception->getMessage()) ? $exception->getMessage() : __('The page has expired due to inactivity. Please refresh and try again.')
])
