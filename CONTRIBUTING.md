## Contributor License Agreement ("CLA")

In order to accept your pull request, we need you to submit a CLA. You only need
to do this once to work on any of Facebook's open source projects.

Complete your CLA here: <https://code.facebook.com/cla>

## Submitting a PR

**All Pull Requests should be made against `dev` (even _bug fixes_).**

Before submitting a large PR for a new feature or improvement, please create an issue first. This will allow us to discuss the feature before much development effort is put into it. After we've agreed that the feature would fit in the scope of the project, or if the change is small enough to not require an issue, follow these steps to create a PR:

- Make a new branch

```
git checkout -b my-fix dev
```

- Make your changes, including test cases if applicable. Make sure to follow the coding guidelines described below.
- Run `hh_client` and `grunt`, and ensure there are no new errors in the output
- Run `extra/format.sh` to apply standard formatting
- Commit your errors with a descriptive message

```
git add my-new-file.php modified-file.php
git commit
```

- Push your changes to Github

```
git push origin my-fix
```

- Create a new [Pull Request](https://help.github.com/articles/using-pull-requests/#initiating-the-pull-request)
- We will review your PR and request changes if necessary.
- When you make a new commit fixing a requested change, please squash your changes using `git rebase`. You can find more info on how to do this [here](https://help.github.com/articles/about-git-rebase/#an-example-of-using-git-rebase).
- Once all requested changes have been made, we will merge your pull request.

## Coding Guidelines

- Wrap lines at 100 characters
- Use spaces not tabs
- New lines should be indented by two spaces
- Always add a trailing comma to multiline lists in Hack
- Never add a trailing comma to multiline lists in JS
- Always run `hh_client` before committing to ensure there are no hack errors
- Always run `grunt` before committing to ensure there are no JS/SCSS errors
 - `eslint` warnings unrelated to your changes are fine, but there should never be more warnings after a commit than before a commit
- Always run `extra/format.sh` before committing to ensure standard formatting
- Don't add extra spaces between parenthesis, i.e. do `foo(1, 2)`, not `foo( 1, 2 )`
- Always use spaces around binary operators, i.e. `$i = 0`, not `$i=0`
- Use CamelCase for member variables/functions and snake_case for local variables/global functions
- Use flow types wherever possible

## Code of Conduct

Facebook has adopted a Code of Conduct that we expect project participants to adhere to. Please [read the full text](https://code.facebook.com/pages/876921332402685/open-source-code-of-conduct) so that you can understand what actions will and will not be tolerated.

As contributors and maintainers of this project, we pledge to respect all people who contribute through reporting issues, posting feature requests, updating documentation, submitting pull requests or patches, and other activities.

We are committed to making participation in this project a harassment-free experience for everyone, regardless of level of experience, gender, gender identity and expression, sexual orientation, disability, personal appearance, body size, race, age, or religion.

Examples of unacceptable behavior by participants include the use of sexual language or imagery, derogatory comments or personal attacks, trolling, public or private harassment, insults, or other unprofessional conduct.

Project maintainers have the right and responsibility to remove, edit, or reject comments, commits, code, wiki edits, issues, and other contributions that are not aligned to this Code of Conduct. Project maintainers who do not follow the Code of Conduct may be removed from the project team.

Instances of abusive, harassing, or otherwise unacceptable behavior may be reported by opening an issue or contacting one or more of the project maintainers.

This Code of Conduct is adapted from the [Contributor Covenant](http://contributor-covenant.org), version 1.0.0, available at [http://contributor-covenant.org/version/1/0/0/](http://contributor-covenant.org/version/1/0/0/)
