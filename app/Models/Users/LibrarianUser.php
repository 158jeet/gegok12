<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Models\Users;

use App\Models\User;

/**
 * Class LibrarianUser
 *
 * Specialized User model for librarian-specific functionality.
 * Extends the base User model with library management features.
 *
 * @property-read \App\Models\LibraryCard $librarycard
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\BookLending[] $lending
 * @mixin \Eloquent
 */
class LibrarianUser extends User
{
    /**
     * Get book lending records managed by this librarian.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLibraryCirculation()
    {
        return $this->school->lending()->paginate(50);
    }

    /**
     * Get all active book borrowings (not returned).
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveLending()
    {
        return $this->lending()
            ->where('return_date', null)
            ->get();
    }

    /**
     * Get overdue book borrowings.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOverdueBooks()
    {
        return $this->lending()
            ->where('return_date', null)
            ->where('expected_return_date', '<', now())
            ->get();
    }

    /**
     * Get library card holders by school.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLibraryMembers()
    {
        return \App\Models\LibraryCard::where('school_id', $this->school_id)->get();
    }
}
