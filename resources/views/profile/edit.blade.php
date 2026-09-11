<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="eyebrow"><span>✦</span> Tài khoản</p>
            <h1 class="account-page-title">Hồ sơ của bạn</h1>
        </div>
    </x-slot>

    <section class="account-page">
        <div class="account-container account-settings-grid">
            <article class="account-card">
                @include('profile.partials.update-profile-information-form')
            </article>

            <article class="account-card">
                @include('profile.partials.update-password-form')
            </article>

            <article class="account-card account-card-danger">
                @include('profile.partials.delete-user-form')
            </article>
        </div>
    </section>
</x-app-layout>
