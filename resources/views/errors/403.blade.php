@extends('errors.base', [
    'code' => '403',
    'title' => __('Access Forbidden'),
    'message' => (isset($exception) && $exception->getMessage()) ? $exception->getMessage() : __('You do not have permission to access this resource.')
])
