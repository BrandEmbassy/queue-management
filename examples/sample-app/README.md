# Messaging Application Example

Sample application to test **brandembassy/queue-management** library. Application can test both AWS SQS and Rabbit producer/consumer.

## How to run

Run one of the following commands:

```
 php ./consumer-sqs.php 
 php ./consumer-rabbit.php 
 php ./producer-sqs.php 
 php ./producer-sqs.php 
```

Currently SQS consumer (started by **consumer-sqs.php**) is utilizing Redis based message deduplication. For this make sure local redis instance is running on default port. If you do not want to use redis based deduplication reconfigure **config-sqs.neon** in follingw way:

* disable **predisClient** and **redisClient** services
* replace **PredisMutex** with other supported mutex implementation (see [available implementations.](https://github.com/php-lock/lock#implementations))

If you do not want to use deduplication at all reconfigure **dedupSvc** to use **BE\QueueManagement\Queue\AWSSQS\MessageDeduplicationDisabled**.

## How SQS components are connecting to AWS

Three well known ways how to connect to AWS are described in [AWS SDK documentation.](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html)
