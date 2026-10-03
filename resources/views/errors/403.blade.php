@extends('errors.layout')
@section('code', '403')
@section('judul', 'Akses ditolak')
@section('pesan', $exception->getMessage() ?: 'Anda tidak memiliki izin untuk membuka halaman ini.')
