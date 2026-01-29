@extends('layouts.app')

@section('title', 'Branding ERP')

@section('content')
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-slate-900">Branding ERP</h1>
            <p class="text-sm text-slate-500">Actualizeaza logo-ul aplicatiei ERP.</p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded border border-blue-100 bg-blue-50 px-4 py-2 text-sm text-blue-800">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data">
            @csrf

            <div class="grid gap-6 md:grid-cols-[200px_1fr]">
                <div class="rounded border border-slate-200 bg-slate-50 p-4 text-center">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Logo ERP" class="mx-auto h-16 w-auto">
                    @else
                        <div class="text-sm text-slate-500">Fara logo incarcat</div>
                    @endif
                </div>

                <div class="space-y-3">
                    <label class="block text-sm font-medium text-slate-700" for="logo">
                        Logo ERP (png, jpg, svg)
                    </label>
                    <input
                        id="logo"
                        name="logo"
                        type="file"
                        accept=".png,.jpg,.jpeg,.svg"
                        class="block w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        required
                    >
                    @error('logo')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button
                    type="submit"
                    class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
                >
                    Salveaza logo
                </button>
            </div>
        </form>
    </div>
@endsection
