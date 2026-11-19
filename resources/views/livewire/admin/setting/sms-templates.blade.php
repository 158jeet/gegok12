<div>
    {{-- Close your eyes. Count to one. That is how long forever feels. --}}

     @if (session()->has('message'))
            <div class="alert alert-success">
                {{ session('message') }}
            </div>
        @endif
      <section class="section">
        <div class="w-full">
            <div class="flex items-center justify-between">
                <h1 class="admin-h1 my-3">Sms Templates</h1>

                {{--<a href="#"
                   class="px-4 py-2 bg-green-600 text-white text-sm rounded
                          hover:bg-green-700 flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                         xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4v16m8-8H4"/>
                    </svg>
                    Add SmsTemplate
                </a> --}}
            </div>
            <div class="p-4 bg-white shadow-lg">

                <div class="w-full">
                    @include('partials.message')
                </div>
                <div class="custom-table overflow-auto">
                    <table class="table table-bordered borderTable">
                        <thead class="bg-grey-light">
                            <tr>
                                <th>Name</th>
                                {{--<th>Content</th>--}}
                                <th>Template</th>
                                <th>Template Id</th>
                                <th>Status</th>
                                <th colspan="4" style="text-align: center;">Action</th>    
                            </tr>
                        </thead>
                        @if(count($sms_templates) > 0)
                            @foreach($sms_templates as $sms_template)
                                <tbody>
                                    <td>
                                        {{ $sms_template->name }}
                                    </td>
                                    {{--<td>
                                        {{ $sms_template->content }}
                                    </td>--}}
                                    <td>
                                        {{ $sms_template->template }}
                                    </td>
                                    <td>
                                        {{ $sms_template->template_id }}
                                    </td>                
                                    <td>
                                        <span class="px-2 py-1 text-xs rounded
                                            {{ $sms_template->status == '0' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                            {{ $sms_template->status == '0' ? 'In-active' : 'Active' }}
                                        </span>

                                    </td>
                                    <td class="py-3 px-2">
                                        <div class="flex items-center">
                                            <a href="{{ route('admin.setting.smstemplate.update',$sms_template->id) }}" title="Edit">
                                                <svg class="w-5 h-5 text-gray-700 hover:text-blue-600 mx-1" fill="currentColor"
                                                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z"/>
                                                    <path d="M20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z"/>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tbody>
                            @endforeach
                        @else
                            <tbody>
                                <td colspan="4" style="text-align: center;"> No Records found</td>
                            </tbody>
                        @endif
                    </table>
                    {{ $sms_templates->links() }}
                </div>
            </div>
        </div>
    </section>
</div>
