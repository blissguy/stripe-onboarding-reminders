# Setting Up the GitHub Repository

Follow these steps to create a GitHub repository for the Stripe Onboarding Reminders plugin:

## 1. Create a New Repository

1. Log in to your GitHub account
2. Click on the "+" icon in the top-right corner and select "New repository"
3. Enter repository details:
   - Repository name: `stripe-onboarding-reminders`
   - Description: "WordPress plugin for sending reminder emails to vendors with incomplete Stripe onboarding in Voxel"
   - Set visibility to Public
   - Check "Add a README file"
   - Choose "GNU General Public License v2.0" from the "Add a license" dropdown
   - Click "Create repository"

## 2. Replace the Default README

1. Replace the default README content with the contents of the `README.md` file from this plugin

## 3. Add Plugin Files

Clone the repository locally and add all plugin files:

```bash
# Clone the repository
git clone https://github.com/stripe-onboarding-reminders.git
cd stripe-onboarding-reminders

# Copy all plugin files into this directory
# (Replace /path/to/plugin with the actual path to your plugin files)
cp -R /path/to/plugin/* .

# Add all files
git add .
git commit -m "Initial commit: Add plugin files"
git push origin main
```

## 4. Create GitHub Releases

Create releases for each version:

1. Go to your repository on GitHub
2. Click on "Releases" in the right sidebar
3. Click "Create a new release"
4. Enter the version tag (e.g., "v1.0.0")
5. Enter a release title (e.g., "Initial Release v1.0.0")
6. Copy the relevant section from CHANGELOG.md into the description
7. Optionally upload a ZIP file of the plugin
8. Click "Publish release"

## 5. Update Plugin Links

After creating the repository, update the following files with your actual GitHub username:

1. `admin/class-admin.php`:

   - In `add_plugin_action_links()`, update the GitHub URL
   - In the "About This Plugin" section, update the documentation and issues links

2. `README.md`:
   - Update all GitHub URLs with your actual username

## 6. Optional: GitHub Pages Documentation

You can set up GitHub Pages to provide more detailed documentation:

1. Go to your repository settings
2. Scroll down to "GitHub Pages"
3. Select "main" branch as the source
4. Choose a theme
5. Save changes
6. Your documentation will be available at https://blissguy.github.io/stripe-onboarding-reminders/
