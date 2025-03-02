#!/bin/bash

# Repackage script for GitHub downloads
# This script takes a GitHub-downloaded ZIP and restructures it with the correct folder name

echo "Stripe Onboarding Reminders - GitHub Download Repackager"
echo "-------------------------------------------------------"

# Check if a filename was provided
if [ $# -eq 0 ]; then
    echo "❌ Error: No ZIP file provided"
    echo "Usage: ./repackage.sh [github-downloaded-zip]"
    echo "Example: ./repackage.sh stripe-onboarding-reminders-main.zip"
    exit 1
fi

GITHUB_ZIP=$1
TEMP_DIR="./temp_repackage"
CORRECT_NAME="stripe-onboarding-reminders"

# Create temp directory
echo "1. Creating temporary directory..."
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR"

# Extract the GitHub zip
echo "2. Extracting GitHub ZIP file..."
unzip -q "$GITHUB_ZIP" -d "$TEMP_DIR"

# Find the extracted directory (usually repo-branch)
EXTRACTED_DIR=$(ls "$TEMP_DIR")
if [ -z "$EXTRACTED_DIR" ]; then
    echo "❌ Error: Could not find extracted directory"
    exit 1
fi

echo "   Found extracted directory: $EXTRACTED_DIR"

# Create the correctly named directory and move files
echo "3. Reorganizing with correct directory structure..."
mkdir -p "$TEMP_DIR/$CORRECT_NAME"
mv "$TEMP_DIR/$EXTRACTED_DIR"/* "$TEMP_DIR/$CORRECT_NAME/"

# Create the properly structured ZIP
echo "4. Creating properly structured ZIP file..."
cd "$TEMP_DIR"
zip -rq "../$CORRECT_NAME.zip" "$CORRECT_NAME"
cd ..

# Clean up
echo "5. Cleaning up temporary files..."
rm -rf "$TEMP_DIR"

echo "✅ Done! Created $CORRECT_NAME.zip with the correct structure for WordPress"
echo "You can now upload this ZIP to your WordPress site for installation/updates" 