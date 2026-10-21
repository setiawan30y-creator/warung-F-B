<?php

namespace App\Livewire;

use App\Livewire\Concerns\TenantPage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Akun saya')]
class AccountPage extends Component
{
    use TenantPage;

    public string $name = '';

    public string $phone = '';

    public string $theme_mode = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $u = $this->user();
        $this->name = $u->name;
        $this->phone = (string) $u->phone;
        $this->theme_mode = (string) $u->theme_mode;
    }

    public function saveProfile(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:120',
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'theme_mode' => 'nullable|in:light,dark,system',
        ], [], ['name' => 'nama', 'phone' => 'telepon']);
        $this->user()->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?: null,
            'theme_mode' => $data['theme_mode'] ?: null,
        ]);
        $this->redirectRoute('account');
        session()->flash('status', 'Akun diperbarui.');
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['current_password' => 'kata sandi saat ini', 'password' => 'kata sandi baru']);
        $this->user()->update(['password' => Hash::make($this->password)]);
        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('toast', message: 'Kata sandi diganti.');
    }

    public function render()
    {
        return view('livewire.account', ['user' => $this->user()->load('roles')]);
    }
}
