<div class="bg-white min-h-screen">
    <section class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="mt-8 sm:mt-12 grid grid-cols-1 lg:grid-cols-3 lg:gap-10">
            <!-- RIGHT SIDE CONTENT FIRST (BUY, CONTACT, DOCS) -->
            <div class="order-1 lg:order-2 bg-white p-6 rounded-2xl shadow-md">
                @if($addondetail['purchase_status'] == false)
                <div class="p-6 border rounded-md bg-gray-50">
                    <p class="text-base font-semibold text-gray-600">One Time</p>
                    <p class="mt-4 text-5xl font-bold text-gray-900">
                        {{ $addondetail['currency_symbol'] }}{{ $addondetail['price'] }}
                    </p>

                    <div class="my-6">
                        <label for="payment_gateway" class="block text-sm font-medium text-gray-700 mb-1">
                            Select Payment Gateway
                        </label>
                        <select wire:model="payment_gateway" wire:change="paymentGatewayChange"
                                id="payment_gateway"
                                class="w-full border border-gray-300 rounded-md py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Payment</option>
                            @foreach($paymentgateways as $gateway)
                                <option value="{{ $gateway['id'] }}">{{ $gateway['displayname'] }}</option>
                            @endforeach
                        </select>
                        @error('payment_gateway')
                        <span class="text-red-600 text-xs">{{ $message }}</span>
                        @enderror
                    </div>

                    @if($gatewayname == 'bank' && !empty($paymentgateway_detail))
                    <div class="mt-4 p-4 border rounded-md bg-yellow-50">
                        <p class="text-sm font-medium mb-2">Bank Transfer Details:</p>
                        <ul class="text-sm text-gray-800 space-y-1">
                            <li><strong>Bank Name:</strong> {{ $paymentgateway_detail['bank_name'] }}</li>
                            <li><strong>Account Name:</strong> {{ $paymentgateway_detail['account_name'] }}</li>
                            <li><strong>Account Number:</strong> {{ $paymentgateway_detail['account_no'] }}</li>
                            <li><strong>IFSC Code:</strong> {{ $paymentgateway_detail['swift_code'] }}</li>
                            <li><strong>Address:</strong> {{ $paymentgateway_detail['bank_address'] }}</li>
                        </ul>
                    </div>
                    @endif

                    <button type="button"
                            wire:click.debounce.500ms="buyAddon"
                            wire:loading.attr="disabled"
                            wire:target="buyAddon"
                            class="block w-full py-2 mt-6 text-white bg-green-600 rounded-md">
                        <span wire:loading.remove wire:target="buyAddon">Buy Now</span>
                        <span wire:loading wire:target="buyAddon">Processing...</span>
                    </button>
                </div>
                @endif

                <!-- CONTACT DETAILS -->
                <div class="mt-8">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Contact Details</h2>
                    <div class="space-y-5">
                        <div class="flex items-start gap-4">
                            <span class="text-gray-600">📍</span>
                            <div>
                                <p class="text-sm font-semibold text-gray-700">Address</p>
                                <p class="text-sm text-gray-600">{{ $contact_detail['contact_address'] }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <span class="text-gray-600">📞</span>
                            <div>
                                <p class="text-sm font-semibold text-gray-700">Phone</p>
                                <p class="text-sm text-gray-600">{{ $contact_detail['contact_number'] }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <span class="text-gray-600">✉️</span>
                            <div>
                                <p class="text-sm font-semibold text-gray-700">Email</p>
                                <p class="text-sm text-gray-600">{{ $contact_detail['contact_email'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                @if(!empty($addondetail['access_token']))
                <div class="mt-6 bg-gray-50 border border-gray-200 rounded-md p-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Access Token</label>
                    <div class="flex items-center">
                        <input type="text"
                               id="accessToken"
                               value="{{ $addondetail['access_token'] }}"
                               readonly
                               class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-l-md bg-white text-gray-800 truncate cursor-text focus:outline-none"
                        />
                        <button type="button"
                                onclick="copyAccessToken()"
                                class="px-3 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-r-md">
                            Copy
                        </button>
                    </div>
                    <p id="copiedMsg" class="text-green-600 text-xs mt-2 hidden">Copied to clipboard!</p>
                </div>
            @endif

                <!-- DOCUMENTATION LINK -->
                <div class="mt-6 text-center">
                    <a href="{{ $addondetail['document_link'] }}" target="_blank" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md shadow">

                    View Documentation
                    </a>

                </div>
            </div>

            <!-- LEFT SIDE CONTENT (Addon Details) -->
            <div class="order-2 lg:order-1 bg-white rounded-2xl p-6 shadow-md lg:col-span-2">
                {{-- Flash Messages --}}
                @if(request('status') == 'success')
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" class="px-4 py-3 rounded mb-4 {{ request('status') === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ request('message') }}
                    </div>
                @endif

                @include('partials.message')

                <h3 class="text-2xl font-bold text-gray-900">
                {{ $addondetail['title'] }}

                @if($addondetail['purchase_status'] == true)
                    <span class="bg-green-600 text-white text-xs font-semibold px-2 py-1 rounded-full">Purchased</span>
                @endif
              </h3>
                <p class="mt-4 text-gray-800">{{ $addondetail['description'] }}</p>

                <!-- Video URLs -->
                @if(!empty($addondetail['addon_urls']))
                    <div class="mt-6">
                        @foreach($addondetail['addon_urls'] as $addon_url)
                            <iframe class="w-full rounded-md mb-4" height="400"
                                    src="{{ $addon_url['url'] ?? '#' }}"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    allowfullscreen>
                            </iframe>
                        @endforeach
                    </div>
                @endif

                <!-- Features -->
                <div class="mt-10 mb-4">
                    <h4 class="text-xl font-semibold text-gray-900 mb-2">What’s Included</h4>
                    <div class="h-px bg-gray-100"></div>
                </div>

                    <ul class="list-disc pl-5 text-gray-700 space-y-1">
                        @if(is_array($addonfeatures) && count($addonfeatures) > 0)
                            @foreach($addonfeatures as $key=>$value)
                            <li>{{$value}}</li>
                            @endforeach 
                        @endif
                    </ul>
                <!-- Screenshot Slider -->
                @if(is_array($addondetail['attachments']) && count($addondetail['attachments']) > 0)
                    <p class="text-2xl font-semibold pt-6 mb-2">Screenshots:</p>
                    <div class="bg-gray-100 p-4 rounded-md">
                        <div>
                            <a id="imageLink" href="{{ $addondetail['attachments'][0]['attachment_file'] }}">
                                <img src="{{ $addondetail['attachments'][0]['attachment_file'] }}"
                                     alt="Screenshot"
                                     class="w-full h-auto rounded-lg shadow-md mb-4"
                                     id="mainImage">
                            </a>
                        </div>
                        <div class="flex gap-4 overflow-x-auto py-2">
                            @foreach($addondetail['attachments'] as $image)
                                <img src="{{ $image['attachment_file'] }}"
                                     alt="Thumbnail"
                                     class="w-24 h-16 object-cover rounded cursor-pointer opacity-60 hover:opacity-100 transition"
                                     onclick="changeImage('{{ $image['attachment_file'] }}')">
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>

@livewireScripts

<script>
    function changeImage(src) {
        document.getElementById('mainImage').src = src;
        document.getElementById('imageLink').href = src;
    }

    Livewire.on('redirect-to-url', (url) => {
        window.open(url, '_blank');
    });
</script>

<script>
    function copyAccessToken() {
        const tokenInput = document.getElementById('accessToken');
        tokenInput.select();
        tokenInput.setSelectionRange(0, 99999); // For mobile devices

        document.execCommand('copy');

        const msg = document.getElementById('copiedMsg');
        msg.classList.remove('hidden');
        setTimeout(() => msg.classList.add('hidden'), 2000);
    }
</script>

