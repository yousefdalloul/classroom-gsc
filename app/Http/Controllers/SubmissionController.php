<?php

namespace App\Http\Controllers;

use App\Models\Classwork;
use App\Models\ClassworkUser;
use App\Models\Submission;
use App\Rules\ForbiddenFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Mockery\Exception;
use Throwable;

class SubmissionController extends Controller
{
    public function store(Request $request, Classwork $classwork)
    {

        Gate::authorize('submissions.create',$classwork);
        // Custom validation using Class Rule
        $request->validate([
            'files' => 'required|array',
            'files.*' => ['file', new ForbiddenFile('text/x-php', 'application/x-http-php', 'application/x-msdownload')],
        ]);
        $assigned = $classwork->users()->where('id', Auth::id())->exists();
        if (!$assigned) {
            abort(403);
        }

        DB::beginTransaction();
        try {
            $data = [];

            foreach ($request->file('files') as $file) {
                $filePath = $file->store("submissions/{$classwork->id}");
                $data[] = [
                    'classwork_id' => $classwork->id,
                    'content' => $filePath,
                    'type' => 'file'
                ];
            }
            $user = Auth::user();
            $user->submissions()->createMany($data);

            ClassworkUser::where([
                'user_id' => $user->id,
                'classwork_id' => $classwork->id,
            ])->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Work Submitted');
    }


    public function file(Submission $submission)
    {
        $user = Auth::user();
        //Check if the user is classroom teacher
        /*
        $collection = DB::select('
        SELECT * FROM classroom_user
        AND role = ?
        WHERE user_id = ?
        AND EXISTS (
            SELECT 1 FROM classwork WHERE classworks.classroom_id = classroom_user.classroom_id
            AND EXISTS (
                SELECT classwork_id FROM submissions WHERE submissions.classwork_id = classworks.id AND id = ?
            )
        )',[$user->id,'teacher',$submission->id]);
         */


        $isTeacher = $submission
            ->classwork
            ->classroom
            ->teachers()
            ->where('id',$user->id)
            ->exists();

        $isOwner = $submission->user_id == $user->id;

        if (!$isTeacher && !$isOwner){
            abort(403);
        }

        // return Storage::disk('local')->download($submission->content);
        return response()->file(storage_path('app/' . $submission->content));
    }
}
