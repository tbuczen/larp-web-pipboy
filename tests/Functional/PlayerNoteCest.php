<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class PlayerNoteCest
{
    public function playersWriteEditAndDeleteTheirOwnNotes(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage($this->notes());
        $I->click('New note');
        $I->submitForm('.pip-note-form', ['title' => 'Vault rumours', 'content' => "The east gate is unguarded.\nAsk the trader."]);
        $I->see('Note saved.');
        $I->see('Vault rumours', 'h2');
        $I->see('The east gate is unguarded.');
        $I->see('Private: only you can read your notes.');

        $I->click('Edit', '.pip-note-actions');
        $I->seeInField('title', 'Vault rumours');
        $I->submitForm('.pip-note-form', ['title' => 'Vault rumours (checked)', 'content' => 'The east gate is guarded now.']);
        $I->see('Vault rumours (checked)', 'h2');
        $I->see('The east gate is guarded now.');

        $I->submitForm('form[action$="/delete"]', []);
        $I->see('Note deleted.');
        $I->dontSee('Vault rumours');
    }

    public function invalidNotesAreNotSaved(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage($this->notes().'?new=1');
        $I->submitForm('.pip-note-form', ['title' => '   ', 'content' => 'No title.']);
        $I->see('Title: This value should not be blank.');
        $I->seeElement('.pip-note-form');

        $I->submitForm('.pip-note-form', ['title' => 'Token check', '_token' => 'invalid']);
        $I->see('The form expired. Please try again.');
        $I->dontSee('Token check', '.pip-list');
    }

    public function organizerNotesAreReadOnlyForPlayers(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage($this->notes());
        $I->click(TestFixture::NOTE_TITLE, '.pip-list');
        $I->see('From the organizers. Read-only.');
        $I->dontSeeElement('.pip-note-actions');
        $noteId = (int) basename($I->grabFromCurrentUrl());

        // Neither the edit form nor a direct write works on an organizer note.
        $I->amOnPage($this->notes().'/'.$noteId.'?edit=1');
        $I->dontSeeElement('.pip-note-form');
        $I->sendAjaxPostRequest('/pipboy/'.TestFixture::$readerPlayerId.'/notes/'.$noteId, ['title' => 'Changed']);
        $I->seeResponseCodeIs(403);
    }

    public function notesStayPrivateFromOtherAccountsAndOrganizers(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage($this->notes().'?new=1');
        $I->submitForm('.pip-note-form', ['title' => 'My secret plan', 'content' => 'Steal the fusion core.']);
        $noteId = (int) basename($I->grabFromCurrentUrl());
        $I->amOnPage('/logout');

        // Another account cannot write to this player's notes.
        $I->signInAs(TestFixture::CHAT_PLAYERS['bob']['email']);
        $I->sendAjaxPostRequest('/pipboy/'.TestFixture::$readerPlayerId.'/notes/'.$noteId.'/delete', []);
        $I->seeResponseCodeIs(404);
        $I->sendAjaxPostRequest('/pipboy/'.TestFixture::$chatPlayerIds['bob'].'/notes', ['title' => 'No notes feature']);
        $I->seeResponseCodeIs(404);
        $I->amOnPage('/logout');

        // Organizers and superadministrators never see notes players write for themselves.
        foreach ([TestFixture::ORGANIZER_EMAIL, TestFixture::ADMIN_EMAIL] as $email) {
            $I->signInAs($email);
            $I->amOnPage('/admin/larp/'.TestFixture::$larpId.'/manage?list=note');
            $I->seeResponseCodeIs(200);
            $I->see(TestFixture::NOTE_TITLE);
            $I->dontSee('My secret plan');
            $I->amOnPage('/admin/note/'.$noteId.'/edit');
            $I->seeResponseCodeIs(403);
            $I->amOnPage('/logout');
        }
    }

    private function notes(): string
    {
        return '/pipboy/'.TestFixture::$readerPlayerId.'/data/notes';
    }
}
