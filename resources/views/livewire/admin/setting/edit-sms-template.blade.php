<div>
    {{-- Update SMS Template --}}

    {{-- Header --}}
    <div class="flex items-center py-3">
        <div class="bg-gray-200 p-1 rounded-full">
            <a href="{{ url('admin/setting/smstemplates') }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke-width="1.5"
                     stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </a>
        </div>

        <div class="text-xl ml-3 font-bold">
            Update SMS Template
        </div>
    </div>

    {{-- Card --}}
    <div class="bg-white shadow px-4 py-4 rounded">

        <form wire:submit.prevent="submitTemplate" method="POST">
            @csrf

            {{-- Template Name 
            <div class="flex flex-col lg:flex-row">
                <div class="w-full lg:w-1/2">
                    <div class="lg:mr-8 mb-4">
                        <label class="tw-form-label">
                            Template Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               class="tw-form-control w-full"
                               placeholder="Event"
                               wire:model.live="name">
                        @error('name')
                            <span class="text-red-600 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>--}}

            {{-- Template ID --}}
            <div class="flex flex-col lg:flex-row">
                <div class="w-full lg:w-1/2">
                    <div class="lg:mr-8 mb-4">
                        <label class="tw-form-label">
                            Template ID <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               class="tw-form-control w-full"
                               wire:model.live="template_id">
                        @error('template_id')
                            <span class="text-red-600 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- SMS Content --}}
            <div class="flex flex-col">
                <div class="w-full">
                    <div class="lg:mr-8 mb-4">
                        <label class="tw-form-label">
                            SMS Content <span class="text-red-500">*</span>
                        </label>
                        <textarea rows="4"
                                  class="tw-form-control w-full"
                                  placeholder="Sms Content."
                                  wire:model.live="template"></textarea>

                        @error('content')
                            <span class="text-red-600 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            {{--Mail Content 
            <div class="flex flex-col">
                <div class="w-full">
                    <div class="lg:mr-8 mb-4">
                        <label class="tw-form-label">
                            Content <span class="text-red-500">*</span>
                        </label>
                        <textarea rows="4"
                                  class="tw-form-control w-full"
                                  wire:model.live="content"></textarea>

                        @error('content')
                            <span class="text-red-600 text-xs">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>--}}


            {{-- Status --}}
            <div class="tw-form-group w-full lg:w-1/2">
                <div class="lg:mr-8 mb-4">
                    <label class="text-sm font-semibold">
                        Status <span class="text-red-500">*</span>
                    </label>

                    <div class="p-2 border border-gray-300 rounded text-sm mt-1">
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2">
                                <input type="radio" wire:model="status" value="1">
                                Active
                            </label>

                            <label class="flex items-center gap-2">
                                <input type="radio" wire:model="status" value="0">
                                Inactive
                            </label>
                        </div>
                    </div>

                    @error('status')
                        <span class="text-red-600 text-xs">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- Actions --}}
            <div class="my-5 flex items-center">
                <div wire:loading.attr="disabled"
                     class="submit-btn btn btn-primary mr-2">
                    <svg wire:loading wire:target="submitTemplate"
                         class="w-4 h-4 mr-1 animate-spin"
                         xmlns="http://www.w3.org/2000/svg"
                         fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25"
                                cx="12" cy="12" r="10"
                                stroke="currentColor"
                                stroke-width="4"/>
                        <path class="opacity-75"
                              fill="currentColor"
                              d="M4 12a8 8 0 018-8V0"/>
                    </svg>
                    <input type="submit"
                           class="text-white bg-transparent cursor-pointer"
                           value="Submit"/>
                </div>

                <a href="{{ url('admin/setting/smstemplates') }}"
                   class="btn btn-reset reset-btn">
                    Cancel
                </a>
            </div>

        </form>
    </div>
</div>
