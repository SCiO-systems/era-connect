<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DbInsertionsController extends Controller
{

    public function saveJournal(Request $request): \Illuminate\Http\JsonResponse
    {
//        get last id of journal table
        $last = DB::table('journal')->latest('journal_id')->first();
        $journalName = $request->input("journal_name");

        $newJournalId = $last->journal_id + 1;

//        insert new entry with id: id_prev + 1
        $savedJournal = DB::table('journal')->insert(
            ['journal_id' => $newJournalId, 'iso_4_code' => 'N/A', 'journal' => $journalName]
        );
        if ($savedJournal != 1) {
            return response()->json(["response" => "Error while inserting new entry."], 500);
        }
        else {
            return response()->json(["response" => "ok", "journal_id" => $newJournalId], 500);

        }
    }
}
