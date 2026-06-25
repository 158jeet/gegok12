<div>
    {{-- Knowing others is intelligence; knowing yourself is true wisdom. --}}
      <section class="section">
        <div class="w-full">
            <div class="flex items-center justify-between">
                <div class="">
                    <h1 class="admin-h1 my-3">Moduels Purchase Histories</h1>
                </div>
            </div>
            <div class="p-4 bg-white shadow-lg">
                <!-- contact -->
                <div class="mb-4 p-4 bg-gray-50 border border--200 rounded-lg shadow-sm">
                    <h2 class="text-lg font-semibold mb-2 text-gray-800">Contact Details</h2>
                    <ul class="text-sm text-gray-700 space-y-1">
                        <li><strong>Email:</strong> {{ $contact_detail['contact_email'] }}</li>
                        <li><strong>Phone:</strong> {{ $contact_detail['contact_number'] }}</li>
                        <li><strong>Address:</strong> {{ $contact_detail['contact_address'] }}</li>
                    </ul>
                </div>
                <!-- end contact -->

                <div class="w-full">
                    @include('partials.message')
                </div>
                    {{-- <div class=" flex flex-wrap items-center mb-3">
                        <select class="tw-form-control text-xs" name="type">
                            <option value="">Filter By Type</option>
                            <option value="alumni" {{ \request()->query('type')== 'alumni' ? 'selected' : '' }}>Alumni</option>
                            <option value="parent" {{ \request()->query('type')== 'parent' ? 'selected' : '' }}>Parent</option>
                            <option value="teacher" {{ \request()->query('type')== 'teacher' ? 'selected' : '' }}>Teacher</option>
                        </select>
                    </div> --}}
                <div class="custom-table overflow-auto">
                    <table class="table table-bordered borderTable">
                        <thead class="bg-grey-light">
                            <tr>
                                <th>Module Name</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Payment Status</th>
                                {{--<th>Paid Date</th>--}}
                                <th>Domain</th>
                                <th>Purchase Date</th>
                                <th>Attachment</th>
                                <th colspan="3" style="text-align: center;">Action</th>    
                            </tr>
                        </thead>
                        @if(!empty($purchase_history_lists))
                            @foreach($purchase_history_lists as $purchase_history)
                                <tbody>
                                    <td>
                                        {{ $purchase_history['addon_name'] }}
                                    </td>
                                    <td>
                                        {{ $purchase_history['currency_symbol'] }}{{ $purchase_history['amount'] }}
                                    </td>
                                    <td>{{ $purchase_history['paymentgateway'] }}</td>          
                                    <td>
                                        @php
                                            $status = strtolower($purchase_history['status']);
                                            $status_classes = [
                                                'pending'   => 'bg-yellow-200 text-yellow-800',
                                                'completed' => 'bg-green-200 text-green-800',
                                                'failed'    => 'bg-red-200 text-red-800',
                                            ];
                                        @endphp
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $status_classes[$status] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ ucfirst($status) }}
                                        </span>
                                    </td>         
                                    {{--<td>
                                        {{ \Carbon\Carbon::parse($purchase_history['paid_at'])->format('d-m-Y H:i') }}
                                    </td>--}}
                                    <td>{{ $purchase_history['domain'] }}</td>
                                    <td>
                                        {{ \Carbon\Carbon::parse($purchase_history['created_at'])->format('d-m-Y H:i') }}
                                    </td>
                                    <td>
                                        @if (!empty($purchase_history['attachment_url']))
                                            <a href="{{ $purchase_history['attachment_url'] }}" target="_blank" class="text-blue-600 hover:underline">
                                                Click to View
                                            </a>
                                        @else
                                            No Attachment
                                        @endif
                                    </td>
                                    <td class="py-3 px-2">
                                        <div class="flex items-center">
                                            <a href="#" wire:click="openUploadModal({{ $purchase_history['id'] }})" class="" title="Upload payment Attachment">
                                                <svg class="w-5 h-5 fill-current text-black-500 mx-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7v2H5v14h14v-7h2v7a2 2 0 0 1-2 2ZM14 2h6v6h-2V5.41l-9.29 9.3-1.42-1.42L16.59 4H14V2Z"/>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tbody>
                            @endforeach
                        @else
                            <tbody>
                                <td colspan="7" style="text-align: center;"> No Records found</td>
                            </tbody>
                        @endif
                    </table>
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


                </div>
            </div>
        </div>
    </section>

    @if($show_attachment)
    <div 
    class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
    x-data 
    x-init="$el.querySelector('input[type=file]')?.focus()"
>
    <div class="bg-white w-full max-w-md p-6 rounded shadow-lg">
        <h2 class="text-lg font-semibold mb-4">Upload Attachment</h2>

        <div class="mb-4">
            <label for="image" class="block text-sm font-medium text-gray-700">Choose Image</label>
            <input 
                wire:model="image" 
                id="image" 
                type="file" 
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200"
            >
            @error('image') <span class="text-xs text-red-600">{{ $message }}</span> @enderror

            @if ($image)
                <div class="mt-4">
                    <p class="text-sm text-gray-600 mb-1">Preview:</p>
                    <img src="{{ $image->temporaryUrl() }}" alt="Preview" class="w-30 h-40 rounded shadow">
                </div>
            @endif
        </div>

        <div class="flex justify-end space-x-2">
            <button 
                wire:click="closeModal()" 
                class="px-4 py-2 text-sm bg-gray-200 rounded hover:bg-gray-300">
                Cancel
            </button>
            <button 
                wire:click="uploadImage" 
                class="px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">
                Upload
            </button>
        </div>
    </div>
</div>
@endif

</div>
