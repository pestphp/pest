<?php

todo('something todo later');

test('something todo later chained')->todo();

test('something todo later chained and with function body', function () {
    expect(true)->toBeFalse();
})->todo();

it('does something within a file with a todo', function () {
    expect(true)->toBeTrue();
});

it('may have an associated assignee', function () {
    expect(true)->toBeTrue();
})->todo(assignee: 'nunomaduro');

it('may have an associated issue', function () {
    expect(true)->toBeTrue();
})->todo(issue: 1);

it('may have an associated PR', function () {
    expect(true)->toBeTrue();
})->todo(pr: 1);

it('may have an associated note', function () {
    expect(true)->toBeTrue();
})->todo(note: 'a note');

describe('todo on describe', function () {
    beforeEach(function () {
        $this->ran = false;
    });

    afterEach(function () {
        match ($this->name()) {
            '__pest_evaluable__todo_on_describe__→__todo_block__→__nested_inside_todo_block__→_it_should_not_execute' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__nested_inside_todo_block__→_it_should_set_the_note' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__describe_with_note__→_it_should_apply_the_note_to_a_test_without_a_todo' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__describe_with_note__→_it_should_apply_the_note_to_a_test_with_a_todo' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__describe_with_note__→_it_should_apply_the_note_as_well_as_the_note_from_the_test' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__describe_with_note__→__nested_describe_with_note__→_it_should_apply_all_parent_notes_to_a_test_without_a_todo' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__describe_with_note__→__nested_describe_with_note__→_it_should_apply_all_parent_notes_to_a_test_with_a_todo' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__describe_with_note__→__nested_describe_with_note__→_it_should_apply_all_parent_notes_as_well_as_the_note_from_the_test' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_describe__→__todo_block__→_it_should_not_execute' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_describe__→_it_should_execute' => expect($this->ran)->toBe(true),
            default => $this->fail('Unexpected test name: '.$this->name()),
        };
    });

    describe('todo block', function () {
        describe('nested inside todo block', function () {
            it('should not execute', function () {
                $this->ran = true;
                $this->fail();
            });

            it('should set the note', function () {
                $this->ran = true;
                $this->fail();
            })->todo(note: 'hi');
        });

        describe('describe with note', function () {
            it('should apply the note to a test without a todo', function () {
                $this->ran = true;
                $this->fail();
            });

            it('should apply the note to a test with a todo', function () {
                $this->ran = true;
                $this->fail();
            })->todo();

            it('should apply the note as well as the note from the test', function () {
                $this->ran = true;
                $this->fail();
            })->todo(note: 'test note');

            describe('nested describe with note', function () {
                it('should apply all parent notes to a test without a todo', function () {
                    $this->ran = true;
                    $this->fail();
                });

                it('should apply all parent notes to a test with a todo', function () {
                    $this->ran = true;
                    $this->fail();
                })->todo();

                it('should apply all parent notes as well as the note from the test', function () {
                    $this->ran = true;
                    $this->fail();
                })->todo(note: 'test note');
            })->todo(note: 'nested describe note');
        })->todo(note: 'describe note');

        it('should not execute', function () {
            $this->ran = true;
            $this->fail();
        });
    })->todo();

    it('should execute', function () {
        $this->ran = true;
        expect($this->ran)->toBe(true);
    });
});

test('todo on test after describe block', function () {
    $this->fail();
})->todo();

test('todo with note on test after describe block', function () {
    $this->fail();
})->todo(note: 'test note');

describe('todo on beforeEach', function () {
    beforeEach(function () {
        $this->ran = false;
    });

    afterEach(function () {
        match ($this->name()) {
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__nested_inside_todo_block__→_it_should_not_execute' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→_it_should_not_execute' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__describe_with_note__→_it_should_apply_the_note_to_a_test_without_a_todo' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__describe_with_note__→_it_should_apply_the_note_to_a_test_with_a_todo' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__describe_with_note__→_it_should_apply_the_note_as_well_as_the_note_from_the_test' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__describe_with_note__→__nested_describe_with_note__→_it_should_apply_all_parent_notes_to_a_test_without_a_todo' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__describe_with_note__→__nested_describe_with_note__→_it_should_apply_all_parent_notes_to_a_test_with_a_todo' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__describe_with_note__→__nested_describe_with_note__→_it_should_apply_all_parent_notes_as_well_as_the_note_from_the_test' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_beforeEach__→_it_should_execute' => expect($this->ran)->toBe(true),
            default => $this->fail('Unexpected test name: '.$this->name()),
        };
    });

    describe('todo block', function () {
        beforeEach()->todo();

        describe('nested inside todo block', function () {
            it('should not execute', function () {
                $this->ran = true;
                $this->fail();
            });
        });

        describe('describe with note', function () {
            it('should apply the note to a test without a todo', function () {
                $this->ran = true;
                $this->fail();
            });

            it('should apply the note to a test with a todo', function () {
                $this->ran = true;
                $this->fail();
            })->todo();

            it('should apply the note as well as the note from the test', function () {
                $this->ran = true;
                $this->fail();
            })->todo(note: 'test note');

            describe('nested describe with note', function () {
                it('should apply all parent notes to a test without a todo', function () {
                    $this->ran = true;
                    $this->fail();
                });

                it('should apply all parent notes to a test with a todo', function () {
                    $this->ran = true;
                    $this->fail();
                })->todo();

                it('should apply all parent notes as well as the note from the test', function () {
                    $this->ran = true;
                    $this->fail();
                })->todo(note: 'test note');
            })->todo(note: 'nested describe note');
        })->todo(note: 'describe note');

        it('should not execute', function () {
            $this->ran = true;
            $this->fail();
        });
    });

    it('should execute', function () {
        $this->ran = true;
        expect($this->ran)->toBe(true);
    });
});

test('todo on test after describe block with beforeEach', function () {
    $this->fail();
})->todo();

test('todo with note on test after describe block with beforeEach', function () {
    $this->fail();
})->todo(note: 'test note');
