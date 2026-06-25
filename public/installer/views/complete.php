<div class="bg-white rounded-2xl card-shadow p-8">
    <div class="text-center mb-8">
        <div class="w-20 h-20 mx-auto bg-gradient-to-br from-green-400 to-emerald-600 rounded-full flex items-center justify-center mb-6 animate-bounce">
            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <h2 class="text-3xl font-bold text-gray-800 mb-2">Installation Complete!</h2>
        <p class="text-gray-600 max-w-md mx-auto">
            Congratulations! Gego K12 has been successfully installed and configured on your server.
        </p>
    </div>

    <div class="bg-green-50 border border-green-200 rounded-xl p-6 mb-8">
        <h3 class="font-semibold text-green-800 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Installation Summary
        </h3>
        <ul class="space-y-2 text-green-700">
            <li class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Environment configuration (.env) created
            </li>
            <li class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Composer dependencies installed
            </li>
            <li class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Database tables created
            </li>
            <li class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Default data and admin account seeded
            </li>
            <li class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Storage directories configured
            </li>
            <li class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Application optimized and cached
            </li>
        </ul>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-8">
        <h3 class="font-semibold text-blue-800 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            Login Credentials
        </h3>
        <p class="text-blue-700 text-sm mb-3">
            Use the default super admin credentials from the database seeder to login. Check your seeder files for the default email and password.
        </p>
        <div class="bg-blue-100 rounded-lg p-3 text-sm">
            <p class="text-blue-800"><strong>Tip:</strong> You can find the default credentials in <code class="bg-blue-200 px-1 rounded">database/seeders</code></p>
        </div>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-8">
        <h3 class="font-semibold text-amber-800 mb-2 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
            Security Recommendation
        </h3>
        <p class="text-amber-700 text-sm">
            For security reasons, please delete or rename the <code class="bg-amber-100 px-1 rounded">/installer</code> directory after verifying the installation works correctly.
        </p>
    </div>

    <div class="text-center">
            <button onclick="window.open(window.location.origin + '/login', '_blank')" class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-semibold rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all transform hover:scale-105 shadow-lg">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                </svg>
                Open Site Login
            </button>
            <p class="mt-4 text-gray-500 text-sm">This will open your site login page in a new window for easy testing.</p>
    </div>
</div>
