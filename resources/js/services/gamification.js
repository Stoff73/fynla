import api from './api';

export default {
  status() {
    return api.get('/gamification/status');
  },
  ackCelebration(level) {
    return api.post('/gamification/celebration/ack', level ? { level } : {});
  },
};
