<div class="bg-white rounded-2xl card-shadow p-8">
    <div class="text-center mb-8">
        <div class="w-16 h-16 mx-auto bg-green-100 rounded-2xl flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Database Configuration</h2>
        <p class="text-gray-600">Enter your MySQL database credentials</p>
    </div>

    <form method="POST" id="dbForm" class="space-y-6">
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <label for="db_host" class="block text-sm font-medium text-gray-700 mb-2">Database Host</label>
                <input type="text" id="db_host" name="db_host" value="127.0.0.1" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                    placeholder="localhost or 127.0.0.1">
            </div>

            <div>
                <label for="db_port" class="block text-sm font-medium text-gray-700 mb-2">Database Port</label>
                <input type="text" id="db_port" name="db_port" value="3306" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                    placeholder="3306">
            </div>
        </div>

        <div>
            <label for="db_name" class="block text-sm font-medium text-gray-700 mb-2">Database Name</label>
            <input type="text" id="db_name" name="db_name" required
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                placeholder="gegok12">
            <p class="text-xs text-gray-500 mt-1">Database will be created if it doesn't exist</p>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <label for="db_user" class="block text-sm font-medium text-gray-700 mb-2">Database Username</label>
                <input type="text" id="db_user" name="db_user" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                    placeholder="root">
            </div>

            <div>
                <label for="db_pass" class="block text-sm font-medium text-gray-700 mb-2">Database Password</label>
                <input type="password" id="db_pass" name="db_pass"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                    placeholder="••••••••">
            </div>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <h4 class="font-semibold text-blue-800 mb-1">Database Tips</h4>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Use a dedicated database user for better security</li>
                        <li>• Make sure the user has full privileges on the database</li>
                        <li>• Use a strong password with mixed characters</li>
                    </ul>
                </div>
            </div>
        </div>

        <div id="testResult" class="hidden"></div>

        <div class="flex justify-between">
            <a href="?step=2" class="inline-flex items-center px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path>
                </svg>
                Back
            </a>

            <div class="flex space-x-3">
                <button type="button" onclick="testConnection()" class="inline-flex items-center px-6 py-3 border border-purple-300 text-purple-700 font-medium rounded-xl hover:bg-purple-50 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Test Connection
                </button>

                <button type="submit" class="inline-flex items-center px-8 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-semibold rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all">
                    Continue
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function testConnection() {
    const form = document.getElementById('dbForm');
    const formData = new FormData(form);
    formData.append('test_connection', '1');

    const resultDiv = document.getElementById('testResult');
    resultDiv.className = 'bg-gray-100 rounded-xl p-4 flex items-center';
    resultDiv.innerHTML = '<svg class="animate-spin w-5 h-5 mr-3 text-purple-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Testing connection...';

    fetch('test-connection.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.className = 'bg-green-50 border border-green-200 rounded-xl p-4 flex items-center';
            resultDiv.innerHTML = '<svg class="w-5 h-5 mr-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span class="text-green-700">' + data.message + '</span>';
        } else {
            resultDiv.className = 'bg-red-50 border border-red-200 rounded-xl p-4 flex items-center';
            resultDiv.innerHTML = '<svg class="w-5 h-5 mr-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg><span class="text-red-700">' + data.message + '</span>';
        }
    })
    .catch(error => {
        resultDiv.className = 'bg-red-50 border border-red-200 rounded-xl p-4 flex items-center';
        resultDiv.innerHTML = '<svg class="w-5 h-5 mr-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg><span class="text-red-700">Connection test failed</span>';
    });
}
</script>
