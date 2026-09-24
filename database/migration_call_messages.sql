-- Add call message type for chat call logs
ALTER TABLE `messages`
  MODIFY `message_type` ENUM('text','image','file','voice','call') NOT NULL DEFAULT 'text';
