# Add Automated Documentation Generation for PHP Functions

## Overview
This PR introduces an automated documentation generation system for PHP functions in the repository. 
The system scans all PHP files in the project, extracts function details from docblocks, and generates a features.md file. 

This file is then hosted on GitHub Pages for easy access.

Additionally, a post-commit Git hook has been added to automate the process of generating and committing the updated features.md file after every commit.

## Key Features

**Automated Docblock Parsing**

Extracts function names, descriptions, parameters (@param), return types (@return), and other annotations from PHP docblocks.

Supports recursive scanning of all .php files in the project.

**Markdown Documentation**

Generates a features.md file in the doc folder.

The file includes:
Function Name
Description
Function Details (e.g., @param, @return)

The function's code in a Markdown code block.

**GitHub Pages Integration**

The features.md file is included in the MkDocs-generated documentation.

A GitHub Actions workflow automatically builds and deploys the documentation to GitHub Pages whenever changes are pushed to the develop branch.

**Post-Commit Hook**

A post-commit Git hook has been added to automate the documentation generation process.

After every commit:
The `generate_features.py` script runs to update the features.md file.

The updated features.md file is automatically staged and included in the commit using `git commit --amend.`

## How It Works

**Python Script**

The generate_features.py script scans the entire project for .php files.

It extracts function details from docblocks and generates the features.md file.

**MkDocs Configuration**

The features.md file is included in the MkDocs navigation under the "Features" section.

The mkdocs.yml file is configured to use the doc folder as the source for documentation.

**GitHub Actions Workflow**

The deploy-docs.yml workflow:
Runs the generate_features.py script.

Builds the MkDocs site.

Deploys the site to GitHub Pages.

**Post-Commit Hook**

The post-commit hook:

Runs the generate_features.py script after every commit.

Stages the updated features.md file.

Amends the current commit to include the updated features.md.

**Usage Instructions**

To Preview Documentation Locally run the following command:

- Install MkDocs and the Material theme

```pip install mkdocs mkdocs-material```

- Serve the documentation locally:

```mkdocs serve```

-  Open ```http://127.0.0.1:8000``` in your browser.

**To Trigger Deployment**

Push changes to the develop branch. The GitHub Actions workflow will automatically build and deploy the documentation to GitHub Pages.

**Post-Commit Hook Setup**

The post-commit hook is located in the hooks directory and is automatically triggered after every commit. It performs the following steps:

1. Runs the generate_features.py script to update the features.md file.
2. Stages the updated features.md file.
3. Amends the current commit to include the updated features.md.

**Post-Commit Hook Script**

Here’s the content of the post-commit hook:
```
#!/bin/bash

# Prevent recursion using an environment variable
if [ "$POST_COMMIT_HOOK" == "1" ]; then
    exit 0
fi

# Set the environment variable to indicate the hook is running
export POST_COMMIT_HOOK=1

# Get the root directory of the Git repository
REPO_ROOT=$(git rev-parse --show-toplevel)

# Run the Python script to generate features.md
python "$REPO_ROOT/generate_features.py"

# Add the updated features.md to the commit
git add "$REPO_ROOT/doc/features.md"

# Amend the current commit to include the updated features.md
git commit --amend --no-edit`
```

<hr>

## Notes for Maintainers

1. Ensure that all PHP functions in the project have properly formatted docblocks for accurate documentation generation.
2. The features.md file is automatically updated whenever the generate_features.py script is run or a commit is made.
3. If the post-commit hook causes issues, it can be temporarily disabled by renaming the post-commit file.