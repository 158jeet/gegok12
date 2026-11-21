<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\Common;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class ImpersonateController extends Controller
{
    use Common;

    public function impersonate($id)
    {
        $user = $this->resolveImpersonationTarget($id);

        abort_if((int) $user->usergroup_id === 3, 403, 'Administrative accounts cannot be impersonated.');
        $this->beginImpersonation($user);

        return redirect('/teacher/dashboard');
    }

    public function librarianimpersonate($id)
    {
        $user = $this->resolveImpersonationTarget($id);

        abort_if((int) $user->usergroup_id === 3, 403, 'Administrative accounts cannot be impersonated.');
        Auth::user()->setImpersonating($user->id);
        $this->auditImpersonation('started', $user);

        return redirect('/library/dashboard');
    }

    public function studentimpersonate($id)
    {
        $user = $this->resolveImpersonationTarget($id);

        abort_if((int) $user->usergroup_id === 3, 403, 'Administrative accounts cannot be impersonated.');
        Auth::user()->setImpersonating($user->id);
        $this->auditImpersonation('started', $user);

        return redirect('/student/dashboard');
    }

    public function schoolAdminimpersonate($id)
    {
        $user = $this->resolveImpersonationTarget($id);

        abort_unless((int) $user->usergroup_id === 3, 403, 'Only a school administration account may be selected here.');
        $actorIsOwner = $this->isTagoreOwner(Auth::user());
        abort_unless($actorIsOwner || (int) Auth::user()->school_id === (int) $user->school_id, 403);
        abort_if($this->isTagoreOwner($user), 403, 'The Owner account cannot be impersonated.');

        Auth::user()->setImpersonating($user->id);
        $this->auditImpersonation('started', $user);

        return redirect('/admin/dashboard');
    }

    public function stopImpersonate()
    {
        if (!Auth::check()) {
            return redirect()->guest(route('login'));
        }

        // The package middleware uses onceUsingId() to impersonate for this
        // request only. stopImpersonating() clears its target flag but does not
        // restore the original guard user, so explicitly restore the persisted
        // login identity before choosing the destination.
        $originalActorId = Session::pull('impersonate_original_user_id')
            ?: Session::get(Auth::getName());

        Auth::user()->stopImpersonating();

        if (!$originalActorId || !Auth::loginUsingId($originalActorId)) {
            Auth::logout();
            return redirect()->guest(route('login'));
        }

        $actor = Auth::user();
        if (!$actor) {
            return redirect()->guest(route('login'));
        }

        if (in_array((int) $actor->usergroup_id, [1, 2, 3], true)) {
            return redirect('/superadmin/dashboard');
        } elseif ((int) $actor->usergroup_id === 5) {
            return redirect('/teacher/dashboard');
        } elseif ((int) $actor->usergroup_id === 6) {
            return redirect('/student/dashboard');
        } elseif ((int) $actor->usergroup_id === 8) {
            return redirect('/library/dashboard');
        }

        return redirect()->route('tagore.dashboard');
    }

    private function beginImpersonation(User $target): void
    {
        $actor = Auth::user();
        abort_unless($actor, 401);

        // Preserve the original login only once, including when a legacy
        // impersonation session is already active.
        if (!$actor->isImpersonating() && !Session::has('impersonate_original_user_id')) {
            Session::put('impersonate_original_user_id', Session::get(Auth::getName()) ?: $actor->getKey());
        }

        $actor->setImpersonating($target->id);
        $this->auditImpersonation('started', $target);
    }

    /**
     * Resolve the target and enforce tenant boundaries before any session mutation.
     * Only the Tagore Owner may cross school boundaries; the Owner itself is never
     * a valid impersonation target.
     */
    private function resolveImpersonationTarget($id): User
    {
        $actor = Auth::user();
        abort_unless($actor, 401);

        $target = User::query()->findOrFail($id);
        abort_if($this->isTagoreOwner($target), 403, 'The Owner account cannot be impersonated.');

        $sameSchool = $actor->school_id !== null
            && (int) $actor->school_id === (int) $target->school_id;

        abort_unless($sameSchool || $this->isTagoreOwner($actor), 403);

        return $target;
    }

    private function isTagoreOwner(User $user): bool
    {
        if (!Schema::hasTable('tagore_user_roles') || !Schema::hasTable('tagore_roles')) {
            return false;
        }

        return DB::table('tagore_user_roles as ur')
            ->join('tagore_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $user->id)
            ->where('ur.status', 'active')
            ->where('r.code', 'OWNER')
            ->exists();
    }

    private function auditImpersonation(string $event, User $target): void
    {
        Log::notice('Legacy user impersonation '.$event.'.', [
            'actor_user_id' => Auth::id(),
            'target_user_id' => $target->id,
            'actor_school_id' => Auth::user()->school_id,
            'target_school_id' => $target->school_id,
            'ip' => request()->ip(),
        ]);
    }
}
