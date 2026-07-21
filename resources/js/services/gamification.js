import api from './api';

export default {
  status() {
    return api.get('/gamification/status');
  },
  ackCelebration() {
    return api.post('/gamification/celebration/ack');
  },
};
