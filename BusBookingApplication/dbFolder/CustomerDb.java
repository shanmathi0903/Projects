package dbFolder;

import beanFolder.Customer;
import java.util.ArrayList;

import java.io.File;
import java.io.FileWriter;
import java.io.IOException;
import java.io.BufferedWriter;
import java.util.Scanner;
import java.io.FileReader;
import java.io.FileNotFoundException;


public class CustomerDb{
	ArrayList<Customer> customer=new ArrayList<>();
	
	int id=1;
	public void addUserDetails(String name,long number,String email,String password){
		customer.add(new Customer(id,name,number,email,password));
		
		try(BufferedWriter bw=new BufferedWriter(new FileWriter("CustomerDetails",true))){
			bw.write(id+","+name+","+number+","+email+","+password);
			bw.newLine();
		}
		catch(IOException e){
			System.out.println(e.getMessage());
		}
			
		System.out.println("Added customer: " + name);
		id++;
	}
	
	public boolean checkAccount(String email,String password){
		
		for(int i=0;i<customer.size();i++){
			if(email.equals(customer.get(i).getEmail()) && password.equals(customer.get(i).getPassword())){
				return true;
			}
		}
		return false;
		/*
		boolean found=false;
		try(Scanner scanner=new Scanner(new FileReader("D:/RDE_Training/BusBookingApplication/CustomerDetails"))){
			while(scanner.hasNextLine()){
				String line=scanner.nextLine();
				if(line.contains(email) && line.contains(password)){
					found=true;
					return true;
				}
			}
		}
		catch(FileNotFoundException e){
			System.out.println(e.getMessage());
		}
		
		return found;
		*/
	}
	
	public void showCustomer(){
		for(int i=0;i<customer.size();i++){
			System.out.println(customer.get(i).getName());
		}
	}
	
	public String findCustomerName(String email,String password){
		String name="";
		for(Customer c:customer){
			if(email.equals(c.getEmail()) && password.equals(c.getPassword())){
				name=c.getName();
			}
		}
		return name;
	}
		
	public long findCustomerNumber(String email,String password){
		long phoneNumber=0L;
		for(Customer c:customer){
			if(email.equals(c.getEmail()) && password.equals(c.getPassword())){
				phoneNumber=c.getPhoneNumber();
			}
		}
		return phoneNumber;
	}
	
}