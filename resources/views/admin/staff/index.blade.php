@extends('layouts.admin.layout')

@section('content')
<div class="relative">

<div class="flex flex-wrap lg:flex-row justify-between my-3">
<div class="">
   <h1 class="admin-h1 my-3">Non-Teaching Staff ( {{ $count }} )</h1>
</div>

   <div class="w-full lg:w-2/4">
   	 <div id="search" ></div>
   	 <div id="teacherfilter" ></div>
   </div>


    <div class="relative flex items-center w-1/4 lg:justify-end">
    <div class="flex items-center" dusk="add-button">
   <a href="{{url('/admin/staff/add/')}}" class="no-underline text-white  px-4 my-3 mx-1 flex items-center custom-green py-1 justify-center">
   <span class="mx-1 text-sm font-semibold">Add</span>
   <svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 409.6 409.6" xml:space="preserve" class="w-3 h-3 fill-current text-white"><g><g><path d="M392.533,187.733H221.867V17.067C221.867,7.641,214.226,0,204.8,0s-17.067,7.641-17.067,17.067v170.667H17.067
         C7.641,187.733,0,195.374,0,204.8s7.641,17.067,17.067,17.067h170.667v170.667c0,9.426,7.641,17.067,17.067,17.067
         s17.067-7.641,17.067-17.067V221.867h170.667c9.426,0,17.067-7.641,17.067-17.067S401.959,187.733,392.533,187.733z"></path></g></g></svg>
   </a> 
   </div>
   
   <staff-export url="{{ url('/') }}" searchquery="{{ $query }}"></staff-export>

   <div class="w-1/3 lg:w-1/4 md:w-1/3 text-center py-4">
        <a href="{{ url('/admin/staffs/id-card/') }}" class="text-xs">
          <div class="bg-gray-200 rounded-full w-8 h-8 lg:w-10 md:h-10 md:w-10 md:h-10 flex items-center justify-center mx-auto hover:bg-gray-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 lg:w-5 lg:h-5 md:w-5 md:h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
</svg>
          </div>
        <span class="hover:font-semibold hidden lg:block md:block">Id card<span>
      </a>
        </div>
  <!--  <div class="">
   <a href="{{ url('/admin/exportStaff/?'.$query) }}" id="export-button" class="no-underline text-white  px-4 my-3 mx-1 flex items-center custom-green py-1">
   <span class="mx-1 text-sm font-semibold">Export</span>
   </a> 
   </div> -->
   
   <!-- <div class="">
   <a href="{{ url('/admin/import/teacher') }}" id="import-button" class="no-underline text-white  px-4 my-3 mx-1 flex items-center custom-green py-1">
   <span class="mx-1 text-sm font-semibold">Import</span>
   </a> 
   </div> -->
   </div>
</div>

@include('partials.message')
<staff-list url="{{url('/')}}"  searchquery="{{$query}}" birthday="{{ $birthday }}" letter="{{ $alphabet }}"></staff-list>

<staff-filter url="{{url('/')}}" searchquery="{{$query}}"></staff-filter>
   
</div>

 @endsection