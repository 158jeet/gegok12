<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use App\Models\Book;

class BookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {

        Validator::extend('check_title', function ($attribute, $value, $parameters, $validator) {
            

            $book=Book::where('title',request('title'))->exists();
                       
            if($book)
            {
              
              return FALSE;
            }
           
            return TRUE;               

        });

        Validator::extend('isbn', function ($attribute, $value, $parameters, $validator) {
            $isbn = str_replace(['-', ' '], '', $value);
            
            // Check ISBN-10
            if (strlen($isbn) === 10) {
                if (!preg_match('/^\d{9}[\dX]$/', $isbn)) {
                    return false;
                }'required|unique:books|isbn'
                $check = 0;
                for ($i = 0; $i < 9; $i++) {
                    $check += (10 - $i) * intval($isbn[$i]);
                }
                $check += ($isbn[9] === 'X') ? 10 : intval($isbn[9]);
                return $check % 11 === 0;
            }
            
            // Check ISBN-13
            if (strlen($isbn) === 13) {
                if (!preg_match('/^\d{13}$/', $isbn)) {
                    return false;
                }
                $check = 0;
                for ($i = 0; $i < 13; $i++) {
                    $check += (($i % 2 === 0) ? 1 : 3) * intval($isbn[$i]);
                }
                return $check % 10 === 0;
            }
            
            return false;
        });
        

        return [
            
            'title'   => 'required|check_title|max:100',
            'author'  => 'required|max:100',
            'category_id'=>'required',
            'book_code'=>'required|unique:books',
            'isbn_number'=>['required','unique:books',new ISBN],
            'availability'=>'required|numeric',
            'cover_image'=>'required|mimes:jpg,jpeg,png',
           
        ];
    }

    public function messages()
    {
        return[
            
            'title.required'     =>  'Title is required',
            'title.check_title'   =>  'Already Exists',
            'author.required'     => 'Author is Required',
            'category_id.required'=>'Select Category',
            'book_code.required'=>'Book Code Required',
            'isbn_number.required'=>'Isbn Number Required',
            'availability.required'=>'Availability Required',
            'cover_image.required'=>'Cover Image Required',
            'cover_image.mimes'=>'Choose jpg,jpeg,png file',
           
        ];
    }
}
