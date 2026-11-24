package com.sciseb;

import com.sciseb.config.AppProperties;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.boot.context.properties.EnableConfigurationProperties;

@SpringBootApplication
@EnableConfigurationProperties(AppProperties.class)
public class SciSebApplication {
    public static void main(String[] args) {
        SpringApplication.run(SciSebApplication.class, args);
    }
}
