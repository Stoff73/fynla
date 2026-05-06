import api from '@/services/api';

const base = '/admin/documents';

export default {
    list() {
        return api.get(base);
    },

    get(id) {
        return api.get(`${base}/${id}`);
    },

    import({ docx, html, images, metadata }) {
        const form = new FormData();
        form.append('docx', docx);
        form.append('html', html);
        Object.entries(metadata || {}).forEach(([k, v]) => {
            if (v != null) form.append(`metadata[${k}]`, v);
        });
        Object.entries(images || {}).forEach(([index, blob]) => {
            const ext = (blob.type && blob.type.split('/')[1]) || 'png';
            const name = `img-${index}.${ext}`;
            const file = new File([blob], name, { type: blob.type || 'image/png' });
            form.append(`images[${index}]`, file);
        });
        return api.post(base, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    update(id, payload) {
        return api.put(`${base}/${id}`, payload);
    },

    destroy(id) {
        return api.delete(`${base}/${id}`);
    },

    publish(id) {
        return api.post(`${base}/${id}/publish`);
    },

    unpublish(id) {
        return api.post(`${base}/${id}/unpublish`);
    },

    previewUrl(id) {
        return api.get(`${base}/${id}/preview-url`);
    },
};
