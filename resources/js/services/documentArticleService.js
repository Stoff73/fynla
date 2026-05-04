import axios from 'axios';

const base = '/api/admin/documents';

export default {
    list() {
        return axios.get(base);
    },

    get(id) {
        return axios.get(`${base}/${id}`);
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
        return axios.post(base, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    update(id, payload) {
        return axios.put(`${base}/${id}`, payload);
    },

    destroy(id) {
        return axios.delete(`${base}/${id}`);
    },

    publish(id) {
        return axios.post(`${base}/${id}/publish`);
    },

    unpublish(id) {
        return axios.post(`${base}/${id}/unpublish`);
    },

    previewUrl(id) {
        return axios.get(`${base}/${id}/preview-url`);
    },
};
