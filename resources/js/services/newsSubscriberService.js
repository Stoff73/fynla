import api from './api';

const newsSubscriberService = {
  async subscribe(email) {
    const { data } = await api.post('/news/subscribe', { email });
    return data;
  },
};

export default newsSubscriberService;
