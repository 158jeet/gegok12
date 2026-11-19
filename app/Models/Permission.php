<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Laratrust\Models\Permission as LaratrustPermission;

/**
 * Class Permission
 *
 * Model for managing application permissions.
 *
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property string $description
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\PermissionUser[] $permissionUser
 * @mixin \Eloquent
 */
class Permission extends LaratrustPermission
{
    protected $table = 'permissions';

    protected $fillable = [
        'name' , 'display_name' , 'description'
    ];

    /**
     * Get permission users associated with this permission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function permissionUser()
    {
        return $this->hasMany('App\Models\PermissionUser','permission_id');
    }
}
