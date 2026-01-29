#!/bin/bash

# Generate placeholder video files using ffmpeg

echo "Generating placeholder videos..."

# Create a simple color video with text overlay
generate_placeholder() {
  local filename=$1
  local title=$2
  local duration=$3
  
  ffmpeg -f lavfi -i color=c=blue:s=1280x720:d=$duration \
         -vf "drawtext=text='$title':fontcolor=white:fontsize=48:x=(w-text_w)/2:y=(h-text_h)/2" \
         -c:v libx264 -pix_fmt yuv420p "assets/videos/$filename.mp4" \
         2>/dev/null || echo "Failed to generate $filename.mp4"
  
  # Generate thumbnail
  ffmpeg -i "assets/videos/$filename.mp4" -ss 00:00:01 -vframes 1 \
         "assets/videos/$filename-thumbnail.jpg" 2>/dev/null
}

# Promo videos
generate_placeholder "promo/intro" "Zewed Platform Introduction" 30
generate_placeholder "promo/features" "Platform Features" 45

# Tutorial videos
generate_placeholder "tutorials/job-search" "How to Search for Jobs" 60
generate_placeholder "tutorials/apply" "How to Apply for Jobs" 90

# Testimonial placeholders
generate_placeholder "testimonials/user1" "User Testimonial" 30

echo "Placeholder videos generated!"
