class ProductGallery {
    constructor(options = {}) {
        this.options = {
            previewContainerId: 'preview-container',
            uploadContainerId: 'upload-container',
            photoInputId: 'photos',
            maxFileSize: 5 * 1024 * 1024,
            ...options
        };
        
        this.previewContainer = document.getElementById(this.options.previewContainerId);
        this.uploadContainer = document.getElementById(this.options.uploadContainerId);
        this.photoInput = document.getElementById(this.options.photoInputId);
        
        this.lightbox = null;
        this.initLightbox();
        this.bindEvents();
    }
    
    initLightbox() {
        const lightbox = document.createElement('div');
        lightbox.className = 'product-lightbox';
        lightbox.innerHTML = `
            <div class="lightbox-overlay"></div>
            <div class="lightbox-container">
                <div class="lightbox-content">
                    <img src="" alt="Product image full view">
                </div>
                <button class="lightbox-close" title="Close">&times;</button>
                <button class="lightbox-prev" title="Previous">&lsaquo;</button>
                <button class="lightbox-next" title="Next">&rsaquo;</button>
                <div class="lightbox-counter"></div>
            </div>
        `;
        document.body.appendChild(lightbox);
        
        this.lightbox = {
            element: lightbox,
            overlay: lightbox.querySelector('.lightbox-overlay'),
            container: lightbox.querySelector('.lightbox-container'),
            image: lightbox.querySelector('.lightbox-content img'),
            closeBtn: lightbox.querySelector('.lightbox-close'),
            prevBtn: lightbox.querySelector('.lightbox-prev'),
            nextBtn: lightbox.querySelector('.lightbox-next'),
            counter: lightbox.querySelector('.lightbox-counter'),
            currentIndex: 0,
            images: []
        };
        
        this.lightbox.closeBtn.addEventListener('click', () => this.closeLightbox());
        this.lightbox.overlay.addEventListener('click', () => this.closeLightbox());
        this.lightbox.prevBtn.addEventListener('click', () => this.navigateLightbox('prev'));
        this.lightbox.nextBtn.addEventListener('click', () => this.navigateLightbox('next'));
        
        document.addEventListener('keydown', (e) => {
            if (!this.lightbox.element.classList.contains('active')) return;
            
            if (e.key === 'Escape') this.closeLightbox();
            if (e.key === 'ArrowLeft') this.navigateLightbox('prev');
            if (e.key === 'ArrowRight') this.navigateLightbox('next');
        });
    }
    
    bindEvents() {
        if (this.photoInput) {
            this.photoInput.addEventListener('change', (e) => this.handleFiles(e.target.files));
        }
        
        if (this.uploadContainer) {
            this.uploadContainer.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.uploadContainer.classList.add('dragover');
            });
            
            this.uploadContainer.addEventListener('dragleave', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.uploadContainer.classList.remove('dragover');
            });
            
            this.uploadContainer.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.uploadContainer.classList.remove('dragover');
                this.handleFiles(e.dataTransfer.files);
            });
        }
        
        document.querySelectorAll('.photo-item img, .preview-item img').forEach((img, index) => {
            img.addEventListener('click', () => this.openLightbox(img.src, index));
        });
    }
    
    handleFiles(files) {
        Array.from(files).forEach(file => {
            if (!file.type.match('image.*')) {
                alert('Hanya file gambar yang diperbolehkan');
                return;
            }
            if (file.size > this.options.maxFileSize) {
                alert(`File ${file.name} terlalu besar. Maksimal 5MB per file`);
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                const preview = document.createElement('div');
                preview.className = 'preview-item';
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-preview" title="Hapus foto">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                this.previewContainer.appendChild(preview);
                
                const previewImg = preview.querySelector('img');
                previewImg.addEventListener('click', () => {
                    this.updateLightboxImages();
                    const newIndex = Array.from(this.previewContainer.querySelectorAll('.preview-item img')).indexOf(previewImg);
                    this.openLightbox(previewImg.src, newIndex);
                });

                preview.querySelector('.remove-preview').addEventListener('click', () => {
                    preview.remove();
                    const dt = new DataTransfer();
                    const { files } = this.photoInput;
                    
                    for (let i = 0; i < files.length; i++) {
                        const f = files[i];
                        if (f !== file) dt.items.add(f);
                    }
                    
                    this.photoInput.files = dt.files;
                    this.updateLightboxImages();
                });
            };
            reader.readAsDataURL(file);
        });
    }
    
    updateLightboxImages() {
        const previewImages = Array.from(this.previewContainer.querySelectorAll('.preview-item img'));
        const galleryImages = Array.from(document.querySelectorAll('.photo-gallery .photo-item img'));
        
        this.lightbox.images = [...previewImages, ...galleryImages].map(img => img.src);
    }
    
    openLightbox(src, index = 0) {
        this.updateLightboxImages();
        
        if (src && this.lightbox.images.includes(src)) {
            index = this.lightbox.images.indexOf(src);
        }
        
        this.lightbox.currentIndex = index;
        this.lightbox.image.src = this.lightbox.images[index];
        this.updateLightboxCounter();
        
        this.lightbox.element.classList.add('active');
        document.body.classList.add('lightbox-open');
    }
    
    closeLightbox() {
        this.lightbox.element.classList.remove('active');
        document.body.classList.remove('lightbox-open');
    }
    
    navigateLightbox(direction) {
        if (this.lightbox.images.length <= 1) return;
        
        if (direction === 'prev') {
            this.lightbox.currentIndex = (this.lightbox.currentIndex - 1 + this.lightbox.images.length) % this.lightbox.images.length;
        } else {
            this.lightbox.currentIndex = (this.lightbox.currentIndex + 1) % this.lightbox.images.length;
        }
        
        this.lightbox.image.src = this.lightbox.images[this.lightbox.currentIndex];
        this.updateLightboxCounter();
    }
    
    updateLightboxCounter() {
        if (this.lightbox.images.length <= 1) {
            this.lightbox.counter.textContent = '';
            this.lightbox.prevBtn.style.display = 'none';
            this.lightbox.nextBtn.style.display = 'none';
        } else {
            this.lightbox.counter.textContent = `${this.lightbox.currentIndex + 1} / ${this.lightbox.images.length}`;
            this.lightbox.prevBtn.style.display = 'block';
            this.lightbox.nextBtn.style.display = 'block';
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const gallery = new ProductGallery();
});