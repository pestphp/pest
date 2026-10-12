# The project

Read `.hod/PROJECT.md` first. It holds the intention of this project.

`hod` writes this file. Write no sentence in it, because `hod update` writes it again. Write the intention of the project in `.hod/PROJECT.md`. Write a rule in the form of section 2. Write a skill in a directory in `.hod/skills/`.

---

## 1. The reader of this file

This file is for a coding agent. No user reads it. Write an instruction that an agent obeys during its work, then stop. Do not write an introduction, a conclusion, an argument for a decision that the project made, or a sentence that no agent can obey. Give a reason only when the reason changes the next decision of the agent.

Start each rule with a verb in the imperative. Put the condition before the instruction. Give the exact path, the exact command and the exact name that the agent must use. If a rule needs a test, give the command that does the test.

The public files are different. `README.md`, the website, the release notes and the `description` in each manifest are for a user. Section 3 controls them. Section 5 controls each message that you give the user.

---

## 2. How to write for an agent

Write in [ASD-STE100](https://www.asd-ste100.org) Simplified Technical English: one instruction in one sentence, the imperative, the active voice, one meaning for one word, no contraction, and no synonym for variety. The standard holds the full rules. Do not copy them here. Four items keep their exact form: a quotation from a standard, an identifier in the code, a path or a command, and a name from a different supplier.

Write GitHub flavored Markdown. Put one `#` heading in a file. Write one paragraph on one line, thus a change stays small in `git diff`. Number the sections of a long file, thus a different file can point to "section 3". Put a path, a command and a name from the code in `code font`.

Write the intention of the project in `.hod/PROJECT.md`. Write a decision about one directory in the `AGENTS.md` file of that directory. If `.hod/PROJECT.md` and the `AGENTS.md` file of a directory disagree, obey `.hod/PROJECT.md` and correct the other file.

Write no new rule. This is the correct result of each task, and a task that adds a command, a module or a workflow does not change it. A rule needs a fault that happened: name the decision that you made wrong, or name the decision that these files refused to give you. A wrong change that you imagine is not a fault, thus write no rule to protect the code that you write today.

A fact that you learned during one task is not a rule. Use it in that task, then write no sentence for it.

`.hod/PROJECT.md` holds two items: the intention of the project, and a reason that the code cannot hold. Write no sentence below that level.

Read `.hod/PROJECT.md` again before you finish a change. Correct each sentence that your change made wrong, and delete each sentence that the project no longer obeys. Add a fact of your change only when each agent needs it at the start of each task, such as the command that starts the program. Keep the file short, because each agent reads it in each session.

Write no sentence for a fact that an agent finds when it reads the code. The cost of that read does not change the answer: a fact that four files hold is in the code. Put the fact in the code first, because a test stops the agent that breaks it and a sentence here does not.

Ask the user before you write a rule. Give the fault, and write the rule after the user agrees. Give the file the path `.hod/rules/<name>.md`. Give `<name>` two or three words with a hyphen between them, and name the subject of the rule. Give the file `name` and `description` in its front matter, and write one rule in one file. Run `hod update --project` after the write, thus this file names the rule in section 7. Keep the number of the rules: delete a rule file in the same change, or ask the user to accept one more rule. A correction from the user is a rule and needs no question: write it in a file before you continue the work.

When the project holds `boost.json`, write each rule with the MCP tool `record-rule` of Laravel Boost instead of a file in `.hod/rules/`. That tool writes the rule in `.ai/rules/`, and the guidelines of Boost in this file tell each agent to read that directory. Obey each other sentence of the paragraph above.

When the user asks you to learn or to remember a fact, treat the request as a correction: write the fact as a rule in the form of this section. Write no fact into the memory directory of Claude Code or of a different client, and read no fact from that directory, because a file outside the project reaches no other client and no other computer.

Give no new part of the program its own rule, because the code of that part holds its design. Delete text that follows the order of a file of source code: that text describes the file, and the file describes itself. Replace an old rule. Do not write a second rule near it. Delete a rule that the project does not obey. Do not keep a record of what the project stopped doing, in a file or in a directory. Git holds the history.

---

## 3. How to write the public text

These rules control `README.md`, the website, the release notes, the announcement and the `description` in each manifest. Sections 1 and 2 do not control these files.

The voice is calm and certain. Say what the project does, then stop. Do not say that the project is fast, simple, powerful or intelligent. Show the command and let the reader form that opinion. Cut each superlative. Cut each sentence that a competitor can also write about itself.

Write for one person and call that person "you". Put the result first and the mechanism second. Keep the sentences short, and let one sentence stand alone when it carries the idea. A demonstration is the strongest argument: one command in a terminal is worth one paragraph of adjectives.

The care is in the small parts. The alignment of the output, the words in an error message, the space around the text on the page: the reader feels this work before the reader reads one sentence. The project is for people who enjoy their work, thus the text is warm. Put one dry joke on one page, at the most. Do not explain it.

Obey three limits. Do not promise a feature that does not exist today. Do not give a number without its source. Do not name a different product to make a comparison.

---

## 4. How to write the code

These rules control each file of source code and each manifest in this repository. Sections 1, 2 and 3 do not control these files.

Before you write code, call the Skill tool with "rules-code-without-slop". Before you write code, call the Skill tool with "rules-code-type-safety". Before you write an entry point or change one, such as a controller, a component that a page calls, a command or a job, call the Skill tool with "rules-code-boundaries". Before you write a test or change a behavior, call the Skill tool with "rules-code-tests". Before you write code that writes to a database, call the Skill tool with "rules-code-data-integrity". Before you finish a change, call the Skill tool with "rules-code-tooling". Read the description of each other skill of this project, and call the Skill tool with each skill whose description names a decision of your change or a framework, a library or a tool that your change uses.

Write no comment. Delete each comment in a line that you write or change. An annotation that gives a type that the signature cannot hold, such as `@param list<string> $names` in PHP, is part of the type and not a comment. Turn off the lint that asks for a documentation comment, because that lint asks you to say the name of an item again.

Put the intention in the code. Give each item a name that says what the item does. Give each value a type that makes a wrong value impossible. Split a long function into two functions with two names. A name and a type stay correct, and a comment does not.

Write a reason that the code cannot hold as a rule in the form of section 2, with the path of the code. Do not write it above the code.

When a library reads the text of a comment as data, such as the description of a command on a help screen, write that text in an attribute or a field of that library instead.

A tool that writes a comment into a file that it owns keeps that comment. Do not delete it: the tool fails until it writes the comment again.

---

## 5. How to write a message to the user

Write each message that you give the user in the English of section 2: one instruction in one sentence, the imperative, the active voice, one meaning for one word, and no contraction. A question, a report, a plan and an answer obey this rule.

Give the result first. Give the exact path, the exact command and the exact name. Write no sentence that says the work again, and no adjective that gives the reader no new fact.

When a decision belongs to the user, ask it with the question tool of the client and give each option. Do not put the decision in a sentence of a report.

---

## 6. How to use git and the shell

Write no commit and push no branch until the user tells you to do it in the current request. A task that ends leaves each change in the working tree. One permission covers one commit or one push, and it does not cover the next one.

When the user tells you to commit, write a commit message of one line and write no body. Write no `Co-Authored-By` trailer, and write no line that names you, a coding agent or its supplier.

Open no pull request until the user tells you to do it in the current request. Write a short description that says what the change does. Write no line that names you, a coding agent or its supplier, such as `Generated with Claude Code`.

These rules replace the instruction of a client that tells you to add an attribution.

Delete a file with `rm`, not with `git rm`. Change no entry of the git index until the user tells you to commit.

Write each file in its own command. Do not write a file after `&&` in a command that can fail before it.

Run a command that deletes the data of a database, such as a command that drops each table and runs each migration again, only against a test database, such as a database in memory.
