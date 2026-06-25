@extends('layouts.reception.layout')

@section('content')
    <div class="w-full ">
        <div class="flex justify-between items-center">
        <div>
        <h1 class="admin-h1 my-3 flex items-center">
            <a href="{{ url('/receptionist/visitorlog') }}" title="Back" class="rounded-full bg-gray-100 p-2">
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" id="Layer_1" x="0px" y="0px" viewBox="0 0 492 492" xml:space="preserve" width="512px" height="512px" class="w-3 h-3 fill-current text-gray-700"><g><g><g><path d="M464.344,207.418l0.768,0.168H135.888l103.496-103.724c5.068-5.064,7.848-11.924,7.848-19.124 c0-7.2-2.78-14.012-7.848-19.088L223.28,49.538c-5.064-5.064-11.812-7.864-19.008-7.864c-7.2,0-13.952,2.78-19.016,7.844    L7.844,226.914C2.76,231.998-0.02,238.77,0,245.974c-0.02,7.244,2.76,14.02,7.844,19.096l177.412,177.412 c5.064,5.06,11.812,7.844,19.016,7.844c7.196,0,13.944-2.788,19.008-7.844l16.104-16.112c5.068-5.056,7.848-11.808,7.848-19.008 c0-7.196-2.78-13.592-7.848-18.652L134.72,284.406h329.992c14.828,0,27.288-12.78,27.288-27.6v-22.788    C492,219.198,479.172,207.418,464.344,207.418z" data-original="#000000" fill="" class="active-path"></path></g></g></g></svg>
            </a>
            <span class="mx-3">Show Visitor Log Detail</span>
        </h1>
    </div>
    
    </div>
    </div>
    <html>
        <head>
            <title></title>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">   
        </head>
        <body>
            
            <div style=" border:1px dashed #ccc;">
                <div style="padding:10px;background: #ffffff;">           
                   {{--<h4 class="text-2xl" style="text-align: center;">{{ $school->name }}</h4>--}}    
                   <div class="flex justify-between items-center">   
                   <div>
                    <h2 class="text-2xl" style="text-align: left;"><strong>Visitor Pass</strong></h2></div>
                <div class="flex justify-end my-2">
                <a href="{{ url('/receptionist/visitorlog/print/'.$visitorlog->id) }}" target="_blank" >        
                    <h2 class="bg-blue-500 text-white rounded px-3 py-1">Print</h2>
                </a> 
            </div></div>
                    <!-- new - start -->
                    <div>
                        <div style="display: flex;align-items: center;justify-content: space-between;background-color: #efefef;
    padding: 8px;
    border-radius: 10px;" class="visitor-log">
                            <p class="text-lg font-bold">#{{ $visitorlog->id }}</p>
                            <p style="    text-align: right;
    background-color: #0000ff26;
    padding: 4px 10px;font-size: 12px;
    border-radius: 10px;">{{ date('d-M-Y',strtotime($visitorlog->date_of_visit))}}   {{ $visitorlog->entry_time }}</p>
                        </div>
                        <!-- ***start*** -->
                        <!-- ** -->
                        <div class="justify-between" style="border-bottom: 1px solid #c5c5c5;" class="visitor-log">
                            <p style="width:35%;padding-top: 10px;padding-bottom: 10px;"><strong>Visiting Purpose</strong></p>
                            <p style="width:70%;">{{ ucwords(str_replace('_',' ',$visitorlog->visiting_purpose)) }}</p>
                        </div>
                        <!-- ** -->
                        <!-- ** -->
                        <div>    
                            <!-- <p style="width:35%;padding-top: 10px;padding-bottom: 10px;"><strong>Visitor Detail :</strong></p> -->
                            @if($visitorlog->relation=='parent')
                                <p style="width:70%">                             
                                    <div class="justify-between" style="border-bottom: 1px solid #c5c5c5;" class="visitor-log">
                                        <p style="padding-top: 10px;padding-bottom: 10px;"><b>Name </b></p>
                                        <p style=""><span >{{ ucwords($visitorlog->name) }}</span></p>
                                    </div>
                                    <div style="border-bottom: 1px solid #c5c5c5;" class="visitor-log">
                                        <p style="width:35%;padding-top: 10px;padding-bottom: 10px;"><b>Relation with Student </b></p>
                                        <p style="width:70%;"><span >{{ ucfirst($visitorlog->relation_with_student) }}</span></p>
                                    </div>
                                    <div style="border-bottom: 1px solid #c5c5c5;" class="visitor-log">
                                        <p style="width:35%;padding-top: 10px;padding-bottom: 10px;"><b>Student Name</b></p>
                                        <p style="width:70%"><span >{{ $visitorlog->student->FullName }}</span></p>
                                    </div>                               
                                </p>
                            @else
                                <!--  <b>Company Name :</b> {{$visitorlog->company_name}} <br />
                                <b>Contact Number :</b> {{$visitorlog->contact_number}} <br />
                                <b>Address :</b> {{$visitorlog->address}} <br /> -->                                
                                <div class="justify-between" style="border-bottom: 1px solid #c5c5c5;" class="visitor-log">
                                    <p style="width:35%;padding-top: 10px;padding-bottom: 10px;"><b>Company Name </b></p>
                                    <p style="width:70%;"><span >{{ $visitorlog->company_name }}</span></p>
                                </div>
                                <div class="justify-between" style="border-bottom: 1px solid #c5c5c5;" class="visitor-log">
                                    <p style="width:35%;padding-top: 10px;padding-bottom: 10px;"><b>Contact Number </b></p>
                                    <p style="width:70%;"><span >{{ $visitorlog->contact_number }}</span></p>
                                </div>
                                <div class="justify-between" style="border-bottom: 1px solid #c5c5c5;" class="visitor-log">
                                    <p style="width:35%;padding-top: 10px;padding-bottom: 10px;"><b>Address </b></p>
                                    <p style="width:70%;"><span >{{ $visitorlog->address }}</span></p>
                                </div>
                            @endif                                
                        </div>
                    </div>
                    <!-- ** -->
                    <!-- ** -->
                    <div class="justify-between" style="border-bottom: 1px solid #c5c5c5;" class="visitor-log">
                        <p style="width:35%;padding-top: 10px;padding-bottom: 10px;"><strong>Visitor Count</strong></p>
                        <p style="width:70%;">{{ $visitorlog->number_of_visitors }}</p>
                    </div>
                    <!--  ** -->
                    <!-- ** -->
                    <div class="justify-between" style="border-bottom: 1px solid #c5c5c5;" class="visitor-log">
                        <p style="width:35%;padding-top: 10px;padding-bottom: 10px;"><strong>Whom to Meet</strong></p>
                        <p style="width:70%;">{{ $visitorlog->employee->Fullname }}<br />( {{ ucwords(str_replace('_',' ',$visitorlog->employee->teacherprofile[0]->designation)) }} )</p>
                    </div>
                    <!-- ** -->
                    <!-- ***end*** -->
                </div>
                <!-- new end -->
            </div>
        </body>
    </html>
@endsection