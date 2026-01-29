// Video Gallery functionality
class VideoGallery {
  constructor() {
    this.videos = [];
    this.currentVideoIndex = 0;
  }

  async initialize() {
    await this.loadVideos();
    this.setupEventListeners();
    this.renderGallery();
  }

  async loadVideos() {
    // In production, this would fetch from an API
    this.videos = [
      {
        id: 'intro',
        title: 'Zewed Platform Introduction',
        description: 'Learn about our platform features and how to get started',
        duration: '2:30',
        views: '1,234',
        date: '2024-01-15',
        thumbnail: 'assets/videos/promo/intro-thumbnail.jpg',
        videoUrl: 'assets/videos/promo/intro.mp4',
        category: 'promo'
      },
      {
        id: 'tutorial-job-search',
        title: 'How to Search for Jobs',
        description: 'Learn how to effectively search and filter job listings',
        duration: '5:45',
        views: '890',
        date: '2024-01-10',
        thumbnail: 'assets/videos/tutorials/job-search-thumbnail.jpg',
        videoUrl: 'assets/videos/tutorials/job-search.mp4',
        category: 'tutorial'
      },
      {
        id: 'testimonial-1',
        title: 'Success Story: Alemayech',
        description: 'How Zewed helped me find my dream job',
        duration: '1:20',
        views: '567',
        date: '2024-01-05',
        thumbnail: 'assets/videos/testimonials/user1-thumbnail.jpg',
        videoUrl: 'assets/videos/testimonials/user1.mp4',
        category: 'testimonial'
      }
    ];
  }

  renderGallery() {
    const container = document.getElementById('video-gallery');
    if (!container) return;

    container.innerHTML = this.videos.map(video => `
      <div class="video-thumbnail" data-video-id="${video.id}">
        <img src="${video.thumbnail}" alt="${video.title}" loading="lazy">
        <div class="video-play-overlay">
          <div class="play-icon">▶</div>
        </div>
        <div class="video-thumbnail-info">
          <h4 class="video-thumbnail-title">${video.title}</h4>
          <div class="video-thumbnail-meta">
            <span>${video.duration}</span>
            <span>${video.views} views</span>
            <span>${video.category}</span>
          </div>
        </div>
      </div>
    `).join('');
  }

  setupEventListeners() {
    // Video thumbnail clicks
    document.addEventListener('click', (e) => {
      const thumbnail = e.target.closest('.video-thumbnail');
      if (thumbnail) {
        const videoId = thumbnail.dataset.videoId;
        this.playVideo(videoId);
      }
      
      // Close modal
      if (e.target.classList.contains('close-modal') || 
          e.target.classList.contains('video-modal')) {
        this.closeModal();
      }
    });

    // Play button in modal
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('play-btn')) {
        const video = document.getElementById('modal-video');
        if (video.paused) {
          video.play();
          e.target.textContent = '⏸';
        } else {
          video.pause();
          e.target.textContent = '▶';
        }
      }
    });

    // Fullscreen button
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('fullscreen-btn')) {
        const video = document.getElementById('modal-video');
        if (video.requestFullscreen) {
          video.requestFullscreen();
        }
      }
    });

    // Progress bar
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('progress-bar')) {
        const progressBar = e.target;
        const rect = progressBar.getBoundingClientRect();
        const percent = (e.clientX - rect.left) / rect.width;
        const video = document.getElementById('modal-video');
        video.currentTime = percent * video.duration;
      }
    });
  }

  playVideo(videoId) {
    const video = this.videos.find(v => v.id === videoId);
    if (!video) return;

    const modal = document.getElementById('video-modal');
    const videoElement = document.getElementById('modal-video');
    const titleElement = document.getElementById('modal-video-title');
    const descElement = document.getElementById('modal-video-desc');

    videoElement.src = video.videoUrl;
    titleElement.textContent = video.title;
    descElement.textContent = video.description;

    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';

    // Play video
    videoElement.play();
  }

  closeModal() {
    const modal = document.getElementById('video-modal');
    const videoElement = document.getElementById('modal-video');
    
    videoElement.pause();
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  }

  // Filter videos by category
  filterVideos(category) {
    if (category === 'all') {
      this.renderGallery();
    } else {
      const filtered = this.videos.filter(video => video.category === category);
      this.renderFilteredGallery(filtered);
    }
  }

  renderFilteredGallery(videos) {
    const container = document.getElementById('video-gallery');
    if (!container) return;

    container.innerHTML = videos.map(video => `
      <div class="video-thumbnail" data-video-id="${video.id}">
        <img src="${video.thumbnail}" alt="${video.title}">
        <div class="video-play-overlay">
          <div class="play-icon">▶</div>
        </div>
        <div class="video-thumbnail-info">
          <h4 class="video-thumbnail-title">${video.title}</h4>
          <div class="video-thumbnail-meta">
            <span>${video.duration}</span>
            <span>${video.views} views</span>
            <span>${video.category}</span>
          </div>
        </div>
      </div>
    `).join('');
  }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
  const videoGallery = new VideoGallery();
  videoGallery.initialize();
});
