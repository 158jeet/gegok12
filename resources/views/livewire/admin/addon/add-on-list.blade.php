<div>
    {{-- Be like water. --}}
<div class="bg-gray-50">
    <section class="py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <!-- Section Header -->
            <div class="text-center mb-12">
                <h2 class="mt-2 text-3xl font-bold text-gray-900 sm:text-4xl">Buy Modules</h2>
                {{-- <p class="mt-4 max-w-2xl mx-auto text-lg text-gray-600">
                    Discover what makes our product stand out from the competition.
                </p> --}}
            </div>

            <!-- Features Grid -->
            <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Feature 1 -->
                @if(!empty($addonsList))
                @foreach($addonsList as $addon)
                <div class="{{ $addon['purchase_status'] ? 'bg-white shadow border' : 'bg-white' }} p-8 rounded-xl relative shadow-md hover:shadow-lg transition-shadow duration-300">
                    @if($addon['purchase_status'])
                        <div class="absolute top-0 right-0 mx-3 my-2 bg-green-600 text-white text-xs font-bold px-2 py-1 rounded-full z-10 shadow">
                            Purchased
                        </div>
                    @endif

                    <div class="flex items-center justify-between">
                        <div class="w-14 h-14 rounded-lg flex items-center justify-start mb-6">
                            <img src="{{ $addon['image'] }}" class="h-16 w-16">
                        </div>
                        @if(!$addon['purchase_status'])
                        <div class="px-3 py-2 rounded text-white" style="background-color: #9b2c2c">
                            <p class="text-xl font-semibold">{{ $addon['currency_symbol'] }}{{ $addon['price'] }}</p>
                        </div>
                        @endif
                    </div>

                    <a href="{{ url('admin/addon/' . $addon['slug'] . '/detail') }}">
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ ucfirst($addon['title']) }}</h3>
                    </a>

                    <p class="text-gray-600">{{ ucfirst($addon['description']) }}.</p>

                    <a href="{{ url('admin/addon/' . $addon['slug'] . '/detail') }}">
                        @php
                            $purchase_name = $addon['purchase_status'] ? 'View' : 'Buy Now';
                        @endphp
                        <div class="px-3 py-2 rounded mx-auto mt-10 {{ $addon['purchase_status'] ? 'bg-blue-600 text-white' : 'bg-gray-700 text-white' }}" style="width: fit-content;">
                            <p class="text-xl font-semibold">{{ $purchase_name }}</p>
                        </div>
                    </a>
                </div>

                @endforeach
                @else
                <div><p>No records found</p></div>

                @endif
            </div>
        </div>

        <!-- pagination -->
        @if (!empty($pagination['meta']) && !empty($pagination['links']))
            @php
                $meta = $pagination['meta'];
                $currentPage = $meta['current_page'];
                $lastPage = $meta['last_page'];

                // Generate page range
                $start = max(1, $currentPage - 2);
                $end = min($lastPage, $currentPage + 2);
            @endphp

            <div class="mt-6 flex justify-center items-center space-x-1 text-sm">
                {{-- Previous Button --}}
                <button
                    wire:click="goToPage({{ $currentPage - 1 }})"
                    class="px-3 py-1 rounded border 
                        {{ $currentPage > 1 ? 'bg-white text-gray-700 hover:bg-gray-100' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}"
                    {{ $currentPage > 1 ? '' : 'disabled' }}>
                    &laquo;
                </button>

                {{-- Page Numbers --}}
                @for ($i = $start; $i <= $end; $i++)
                    <button
                        wire:click="goToPage({{ $i }})"
                        class="px-3 py-1 rounded border 
                            {{ $i == $currentPage ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' }}">
                        {{ $i }}
                    </button>
                @endfor

                {{-- Next Button --}}
                <button
                    wire:click="goToPage({{ $currentPage + 1 }})"
                    class="px-3 py-1 rounded border 
                        {{ $currentPage < $lastPage ? 'bg-white text-gray-700 hover:bg-gray-100' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}"
                    {{ $currentPage < $lastPage ? '' : 'disabled' }}>
                    &raquo;
                </button>
            </div>
        @endif
        <!-- end pagination -->
    </section>
     
</div>
</div>
