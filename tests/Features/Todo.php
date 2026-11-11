<?php

todo('something todo later');

test('something todo later chained')->todo();

test('something todo later chained and with function body', function (): void {
    expect(true)->toBeFalse();
})->todo();

it('does something within a file with a todo', function (): void {
    expect(true)->toBeTrue();
});

it('may have an associated assignee', function (): void {
    expect(true)->toBeTrue();
})->todo(assignee: 'nunomaduro');

it('may have an associated issue', function (): void {
    expect(true)->toBeTrue();
})->todo(issue: 1);

it('may have an associated PR', function (): void {
    expect(true)->toBeTrue();
})->todo(pr: 1);

it('may have an associated note', function (): void {
    expect(true)->toBeTrue();
})->todo(note: 'a note');

describe('todo on describe', function (): void {
    beforeEach(function (): void {
        $this->ran = false;
    });

    afterEach(function (): void {
        match ($this->name()) {
            '__pest_evaluable__todo_on_describe__→__todo_block__→__nested_inside_todo_block__→_it_should_not_execute' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__nested_inside_todo_block__→_it_should_set_the_note' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__describe_with_note__→_it_should_apply_the_note_to_a_test_without_a_todo' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__describe_with_note__→_it_should_apply_the_note_to_a_test_with_a_todo' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__describe_with_note__→_it_should_apply_the_note_as_well_as_the_note_from_the_test' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__describe_with_note__→__nested_describe_with_note__→_it_should_apply_all_parent_notes_to_a_test_without_a_todo' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__describe_with_note__→__nested_describe_with_note__→_it_should_apply_all_parent_notes_to_a_test_with_a_todo' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_describe__→__todo_block__→__describe_with_note__→__nested_describe_with_note__→_it_should_apply_all_parent_notes_as_well_as_the_note_from_the_test' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_describe__→__todo_block__→_it_should_not_execute' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_describe__→_it_should_execute' => expect($this->ran)->toBeTrue(),
            default => $this->fail('Unexpected test name: '.$this->name()),
        };
    });

    describe('todo block', function (): void {
        describe('nested inside todo block', function (): void {
            it('should not execute', function (): void {
                $this->ran = true;
                $this->fail();
            });

            it('should set the note', function (): void {
                $this->ran = true;
                $this->fail();
            })->todo(note: 'hi');
        });

        describe('describe with note', function (): void {
            it('should apply the note to a test without a todo', function (): void {
                $this->ran = true;
                $this->fail();
            });

            it('should apply the note to a test with a todo', function (): void {
                $this->ran = true;
                $this->fail();
            })->todo();

            it('should apply the note as well as the note from the test', function (): void {
                $this->ran = true;
                $this->fail();
            })->todo(note: 'test note');

            describe('nested describe with note', function (): void {
                it('should apply all parent notes to a test without a todo', function (): void {
                    $this->ran = true;
                    $this->fail();
                });

                it('should apply all parent notes to a test with a todo', function (): void {
                    $this->ran = true;
                    $this->fail();
                })->todo();

                it('should apply all parent notes as well as the note from the test', function (): void {
                    $this->ran = true;
                    $this->fail();
                })->todo(note: 'test note');
            })->todo(note: 'nested describe note');
        })->todo(note: 'describe note');

        it('should not execute', function (): void {
            $this->ran = true;
            $this->fail();
        });
    })->todo();

    it('should execute', function (): void {
        $this->ran = true;
        expect($this->ran)->toBeTrue();
    });
});

describe('todo on describe with matching name', function (): void {
    beforeEach(function (): void {
        $this->ran = false;
    });

    afterEach(function (): void {
        match ($this->name()) {
            '__pest_evaluable__todo_on_describe_with_matching_name__→__describe_block__→_it_should_not_execute' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_describe_with_matching_name__→__describe_block__→_it_should_execute_a_test_in_a_describe_block_with_the_same_name_as_a_todo_describe_block' => expect($this->ran)->toBeTrue(),
            '__pest_evaluable__todo_on_describe_with_matching_name__→_it_should_execute' => expect($this->ran)->toBeTrue(),

            default => $this->fail('Unexpected test name: '.$this->name()),
        };
    });

    describe('describe block', function (): void {
        it('should not execute', function (): void {
            $this->ran = true;
            $this->fail();
        });
    })->todo();

    describe('describe block', function (): void {
        it('should execute a test in a describe block with the same name as a todo describe block', function (): void {
            $this->ran = true;
        });
    });

    it('should execute', function (): void {
        $this->ran = true;
        expect($this->ran)->toBeTrue();
    });
});

test('todo on test after describe block', function (): void {
    $this->fail();
})->todo();

test('todo with note on test after describe block', function (): void {
    $this->fail();
})->todo(note: 'test note');

describe('todo on beforeEach', function (): void {
    beforeEach(function (): void {
        $this->ran = false;
    });

    afterEach(function (): void {
        match ($this->name()) {
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__nested_inside_todo_block__→_it_should_not_execute' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→_it_should_not_execute' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__describe_with_note__→_it_should_apply_the_note_to_a_test_without_a_todo' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__describe_with_note__→_it_should_apply_the_note_to_a_test_with_a_todo' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__describe_with_note__→_it_should_apply_the_note_as_well_as_the_note_from_the_test' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__describe_with_note__→__nested_describe_with_note__→_it_should_apply_all_parent_notes_to_a_test_without_a_todo' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__describe_with_note__→__nested_describe_with_note__→_it_should_apply_all_parent_notes_to_a_test_with_a_todo' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__describe_with_note__→__nested_describe_with_note__→_it_should_apply_all_parent_notes_as_well_as_the_note_from_the_test' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__todo_on_beforeEach__→_it_should_execute' => expect($this->ran)->toBeTrue(),
            default => $this->fail('Unexpected test name: '.$this->name()),
        };
    });

    describe('todo block', function (): void {
        beforeEach()->todo();

        describe('nested inside todo block', function (): void {
            it('should not execute', function (): void {
                $this->ran = true;
                $this->fail();
            });
        });

        describe('describe with note', function (): void {
            it('should apply the note to a test without a todo', function (): void {
                $this->ran = true;
                $this->fail();
            });

            it('should apply the note to a test with a todo', function (): void {
                $this->ran = true;
                $this->fail();
            })->todo();

            it('should apply the note as well as the note from the test', function (): void {
                $this->ran = true;
                $this->fail();
            })->todo(note: 'test note');

            describe('nested describe with note', function (): void {
                it('should apply all parent notes to a test without a todo', function (): void {
                    $this->ran = true;
                    $this->fail();
                });

                it('should apply all parent notes to a test with a todo', function (): void {
                    $this->ran = true;
                    $this->fail();
                })->todo();

                it('should apply all parent notes as well as the note from the test', function (): void {
                    $this->ran = true;
                    $this->fail();
                })->todo(note: 'test note');
            })->todo(note: 'nested describe note');
        })->todo(note: 'describe note');

        it('should not execute', function (): void {
            $this->ran = true;
            $this->fail();
        });
    });

    it('should execute', function (): void {
        $this->ran = true;
        expect($this->ran)->toBeTrue();
    });
});

test('todo on test after describe block with beforeEach', function (): void {
    $this->fail();
})->todo();

test('todo with note on test after describe block with beforeEach', function (): void {
    $this->fail();
})->todo(note: 'test note');
