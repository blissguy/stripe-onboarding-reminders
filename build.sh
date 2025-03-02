#!/bin/bash

# Build script for Stripe Onboarding Reminders WordPress plugin run
# This script creates a properly structured ZIP file for distribution

# Get the current version from the main plugin file
VERSION=$(grep "Version:" stripe-onboarding-reminders.php | awk -F': ' '{print $2}' | tr -d '\r')

echo "Building Stripe Onboarding Reminders v${VERSION}..."

# Remove any existing build directory and zip
rm -rf ./build
rm -f ./stripe-onboarding-reminders.zip
rm -f ./stripe-onboarding-reminders-${VERSION}.zip

# Create build directory with correct plugin name
mkdir -p ./build/stripe-onboarding-reminders

# Copy all plugin files to the build directory
# Excluding development files and the build directory itself
rsync -av --exclude='.git' \
          --exclude='.github' \
          --exclude='.gitignore' \
          --exclude='build' \
          --exclude='build.sh' \
          --exclude='repackage.sh' \
          --exclude='*.zip' \
          --exclude='.DS_Store' \
          --exclude='node_modules' \
          ./ ./build/stripe-onboarding-reminders/

# Create the zip file with the correct structure
cd ./build
zip -r ../stripe-onboarding-reminders.zip ./stripe-onboarding-reminders
cd ..

# Also create a versioned zip for release purposes
cp stripe-onboarding-reminders.zip stripe-onboarding-reminders-${VERSION}.zip

echo "✅ Build complete!"
echo "Created:"
echo "  - stripe-onboarding-reminders.zip (for updates)"
echo "  - stripe-onboarding-reminders-${VERSION}.zip (for releases)" 