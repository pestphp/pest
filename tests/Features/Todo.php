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
        });

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

describe('todo on beforeEach', function () {
    beforeEach(function () {
        $this->ran = false;
    });

    afterEach(function () {
        match ($this->name()) {
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→__nested_inside_todo_block__→_it_should_not_execute' => expect($this->ran)->toBe(false),
            '__pest_evaluable__todo_on_beforeEach__→__todo_block__→_it_should_not_execute' => expect($this->ran)->toBe(false),
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
