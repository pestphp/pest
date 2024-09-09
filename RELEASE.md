# Release process

When releasing a new version of Pest there are some checks and updates that need to be done:

> **For Pest v2 you should use the `2.x` branch instead.**

- Clear your local repository with: `git add . && git reset --hard && git checkout 3.x`
- On the GitHub repository, check the contents of [github.com/pestphp/pest/compare/{latest_version}...3.x](https://github.com/pestphp/pest/compare/{latest_version}...3.x)
- Update the version number in [src/Pest.php](src/Pest.php)
- Run the tests locally using: `composer test`
- Commit the Pest file with the message: `git commit -m "release: vX.X.X"`
- Push the changes to GitHub
- Check that the CI is passing as expected: [github.com/pestphp/pest/actions](https://github.com/pestphp/pest/actions)
- Tag and push the tag with `git tag vX.X.X && git push --tags`
- Publish release here: [github.com/pestphp/pest/releases/new](https://github.com/pestphp/pest/releases/new).

### Plugins

Plugins should be versioned using the same major (or minor for `0.x` releases) version as Pest core.
