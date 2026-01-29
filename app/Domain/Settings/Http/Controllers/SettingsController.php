<?php

namespace App\Domain\Settings\Http\Controllers;

use App\Domain\Settings\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private SettingsService $settings)
    {
    }

    public function editBranding(): View
    {
        $this->ensureAdmin();

        $logoPath = $this->settings->get('branding.logo_path');
        $logoUrl = $logoPath ? Storage::url($logoPath) : null;

        return view('admin.settings.branding', [
            'logoPath' => $logoPath,
            'logoUrl' => $logoUrl,
        ]);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,svg'],
        ]);

        $file = $data['logo'];
        $extension = $file->getClientOriginalExtension();

        $path = $file->storeAs('erp', 'logo.' . $extension, 'public');
        $this->settings->set('branding.logo_path', $path);

        return redirect()
            ->route('admin.settings.branding')
            ->with('status', 'Logo actualizat.');
    }

    private function ensureAdmin(): void
    {
        $user = auth()->user();

        if (!$user || !$user->isAdmin()) {
            abort(403);
        }
    }
}
