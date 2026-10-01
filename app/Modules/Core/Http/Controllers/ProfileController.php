<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $layout = $request->user()->isOperator() ? 'layouts.operator' : 'layouts.admin';
        // Users can review their own recent sign-ins (spot unexpected access).
        return view('core.profile', ['user' => $request->user(), 'layout' => $layout,
            'logins' => \App\Models\LoginLog::where('user_id', $request->user()->id)->latest('id')->limit(8)->get()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
        ]);
        $data['email'] = strtolower($data['email']);
        $user->fill($data);
        AuditService::logChanges('auth', $user, 'profile_updated');
        $user->save();
        return back()->with('success', 'Profile saved.');
    }

    /** Saves the theme toggle choice for signed-in users (called by app.js). */
    public function theme(Request $request)
    {
        $data = $request->validate(['theme' => ['required', Rule::in(['light', 'dark', 'system'])]]);
        $request->user()?->forceFill(['theme' => $data['theme']])->save();
        return response()->json(['ok' => true, 'theme' => $data['theme']]);
    }
}
